<?php
namespace app\components;

/** SIP-021: bounded, verified server-to-server login; never returns upstream error text. */
class NapayLoginClient extends \yii\base\Component
{
    public $baseUrl = 'https://napay.local/api/pos/v1';
    public $caFile = '/run/pos-secrets/ca.crt';
    public $clientKeyFile = '/run/pos-secrets/client-key';

    public function login(string $email, string $password, ?string $ip = null): array
    {
        try {
            if ($this->baseUrl !== 'https://napay.local/api/pos/v1'
                || !is_file($this->caFile) || !is_readable($this->caFile)
                || !is_file($this->clientKeyFile) || !is_readable($this->clientKeyFile)) return $this->unavailable();
            $key = @file_get_contents($this->clientKeyFile, false, null, 0, 256);
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', trim($key))) return $this->unavailable();
            $body = json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (strlen($body) > 4096) return $this->unavailable();
            if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP) === false) return $this->unavailable();
            $response = $this->send($body, trim($key), $ip);
            if (strlen($response['body'] ?? '') > 16384 || !empty($response['setCookie'])
                || !preg_match('~^application/json(?:\s*;|$)~i', $response['type'] ?? '')) return $this->unavailable();
            $data = json_decode($response['body'], true, 8, JSON_THROW_ON_ERROR);
            if ($response['status'] !== 201) {
                $code = $data['error']['code'] ?? null;
                if ($response['status'] === 401 && $code === 'invalid_credentials') return ['ok' => false, 'error' => 'invalid_credentials'];
                if ($response['status'] === 429 && $code === 'rate_limited') {
                    $retry = filter_var($response['retry'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 900]]);
                    return ['ok' => false, 'error' => 'rate_limited', 'retry' => $retry === false ? 60 : $retry];
                }
                return $this->unavailable();
            }
            if (!is_array($data) || count($data) !== 4 || ($data['token_type'] ?? null) !== 'Bearer'
                || !is_string($data['access_token'] ?? null) || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D', $data['access_token'])
                || !is_string($data['expires_at'] ?? null) || !is_array($data['operator'] ?? null)
                || count($data['operator']) !== 1 || !is_string($data['operator']['display_name'] ?? null)
                || !mb_check_encoding($data['operator']['display_name'], 'UTF-8') || mb_strlen($data['operator']['display_name']) > 513) return $this->unavailable();
            $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $data['expires_at'], new \DateTimeZone('UTC'));
            if (!$expiry || $expiry->format('Y-m-d\TH:i:s\Z') !== $data['expires_at']
                || $expiry->getTimestamp() <= time() || $expiry->getTimestamp() > time() + 86400) return $this->unavailable();
            return ['ok' => true, 'session' => ['token' => $data['access_token'], 'expires' => $expiry->getTimestamp(), 'name' => $data['operator']['display_name']]];
        } catch (\Throwable $error) {
            // No exception message, request/response body or credentials enter logs/UI.
            return $this->unavailable();
        }
    }

    protected function send(string $body, string $key, ?string $ip): array
    {
        return $this->request('POST', $body, $key, $ip);
    }

    public function logout(string $token): array
    {
        try {
            if ($this->baseUrl !== 'https://napay.local/api/pos/v1'
                || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D', $token)
                || !is_readable($this->caFile) || !is_readable($this->clientKeyFile)) return $this->unavailable();
            $key = @file_get_contents($this->clientKeyFile, false, null, 0, 256);
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', trim($key))) return $this->unavailable();
            $response = $this->request('DELETE', '', trim($key), null, $token);
            if (($response['status'] ?? null) === 204 && ($response['body'] ?? null) === '' && empty($response['setCookie'])) return ['ok' => true];
            return $this->unavailable();
        } catch (\Throwable $error) {
            return $this->unavailable();
        }
    }

    protected function request(string $method, string $body, string $key, ?string $ip, ?string $token = null): array
    {
        $curl = curl_init($this->baseUrl . '/sessions');
        $raw = ''; $retry = null; $setCookie = false;
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Expect:', 'X-POS-Client-Key: ' . $key];
        if ($ip !== null) $headers[] = 'X-POS-Operator-IP: ' . $ip;
        if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
        try {
            curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CAINFO => $this->caFile,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
                    if (strlen($raw) + strlen($chunk) > 16384) return 0;
                    $raw .= $chunk; return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$retry, &$setCookie): int {
                    if (stripos($header, 'Retry-After:') === 0) $retry = trim(substr($header, 12));
                    if (stripos($header, 'Set-Cookie:') === 0) $setCookie = true;
                    return strlen($header);
                },
            ]);
            if (curl_exec($curl) === false) return [];
            return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'type' => curl_getinfo($curl, CURLINFO_CONTENT_TYPE),
                'body' => $raw, 'retry' => $retry, 'setCookie' => $setCookie];
        } finally {
            curl_close($curl);
        }
    }

    private function unavailable(): array
    {
        return ['ok' => false, 'error' => 'backend_unavailable'];
    }
}
