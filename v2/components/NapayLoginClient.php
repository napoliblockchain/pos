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
        } catch (\Throwable) {
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
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    protected function request(string $method, string $body, string $key, ?string $ip, ?string $token = null): array
    {
        return $this->transport($this->baseUrl . '/sessions', $method, $body, $key, $ip, $token);
    }

    public function catalog(string $token, ?string $cursor = null, int $limit = 50): array
    {
        try {
            if ($this->baseUrl !== 'https://napay.local/api/pos/v1' || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D',$token)
                || $limit < 1 || $limit > 100 || ($cursor !== null && !preg_match('/^[A-Za-z0-9_-]{1,512}$/D',$cursor))
                || !is_readable($this->caFile) || !is_readable($this->clientKeyFile)) return $this->unavailable();
            $key = @file_get_contents($this->clientKeyFile,false,null,0,256);
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{43}$/D',trim($key))) return $this->unavailable();
            $params = ['limit'=>$limit]; if ($cursor !== null) $params['cursor']=$cursor;
            $response = $this->transport($this->baseUrl.'/terminals?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),'GET','',trim($key),null,$token);
            if (strlen($response['body'] ?? '') > 16384 || !empty($response['setCookie'])
                || !preg_match('~^application/json(?:\s*;|$)~i',$response['type'] ?? '')) return $this->unavailable();
            $data = json_decode($response['body'],false,8,JSON_THROW_ON_ERROR);
            if (($response['status'] ?? null) !== 200) {
                $code = $data->error->code ?? null;
                if (($response['status'] ?? null) === 401 && $code === 'invalid_token') return ['ok'=>false,'error'=>'invalid_token'];
                if (($response['status'] ?? null) === 400 && $code === 'validation_failed') return ['ok'=>false,'error'=>'validation_failed'];
                if (($response['status'] ?? null) === 429 && $code === 'rate_limited') {
                    $retry=filter_var($response['retry'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>60]]);
                    return ['ok'=>false,'error'=>'rate_limited','retry'=>$retry === false ? 60 : $retry];
                }
                return $this->unavailable();
            }
            if (!$data instanceof \stdClass || count(get_object_vars($data)) !== 2 || !property_exists($data,'next_cursor')
                || !is_array($data->items ?? null) || count($data->items) > $limit) return $this->unavailable();
            if ($data->next_cursor !== null && (!is_string($data->next_cursor) || !preg_match('/^[A-Za-z0-9_-]{1,512}$/D',$data->next_cursor)
                || $data->items === [] || $data->next_cursor === $cursor)) return $this->unavailable();
            $items=[]; $ids=[];
            foreach ($data->items as $item) {
                if (!$item instanceof \stdClass || count(get_object_vars($item)) !== 4 || ($item->availability ?? null) !== 'unknown'
                    || !is_string($item->id ?? null) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$item->id)
                    || isset($ids[$item->id])) return $this->unavailable();
                foreach (['name','store_name'] as $field) {
                    if (!is_string($item->$field ?? null) || !mb_check_encoding($item->$field,'UTF-8')
                        || mb_strlen($item->$field) > 256 || !preg_match('/\S/u',$item->$field)) return $this->unavailable();
                }
                $items[]=get_object_vars($item); $ids[$item->id]=true;
            }
            return ['ok'=>true,'catalog'=>['items'=>$items,'next_cursor'=>$data->next_cursor]];
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /**
     * SIP-021 fase 2: aperture esclusive. Esiti: ok + opening, oppure errore pubblico tra terminal_in_use,
     * not_found, opening_invalid, invalid_token, rate_limited (con retry), backend_unavailable. Mai testo upstream.
     */
    public function openTerminal(string $token, string $terminalId, string $instanceId, string $idempotencyKey): array
    {
        if (!self::uuid($terminalId) || !self::uuid($instanceId) || !self::uuid($idempotencyKey)) return $this->unavailable();
        return $this->opening('POST', '/terminals/' . $terminalId . '/openings', $token, ['instance_id' => $instanceId], $idempotencyKey, [200, 201], $terminalId, $instanceId);
    }

    public function viewOpening(string $token, array $opening): array
    {
        return self::validOpening($opening) ? $this->opening('GET', '/openings/' . $opening['id'], $token, null, null, [200], $opening['terminal_id'], $opening['instance_id'], $opening) : $this->unavailable();
    }

    public function heartbeat(string $token, array $opening): array
    {
        return self::validOpening($opening) ? $this->opening('POST', '/openings/' . $opening['id'] . '/heartbeat', $token,
            ['instance_id' => $opening['instance_id'], 'generation' => $opening['generation']], null, [204]) : $this->unavailable();
    }

    public function release(string $token, array $opening): array
    {
        return self::validOpening($opening) ? $this->opening('POST', '/openings/' . $opening['id'] . '/release', $token,
            ['instance_id' => $opening['instance_id'], 'generation' => $opening['generation']], null, [204]) : $this->unavailable();
    }

    /**
     * SIP-021 fase 3: richiesta di pagamento TTS dall'apertura. Esiti: ok + payment, oppure errore pubblico tra
     * opening_invalid, idempotency_conflict, payment_unavailable, not_found, invalid_token, validation_failed,
     * rate_limited (con retry), backend_unavailable.
     */
    public function createPayment(string $token, array $opening, int $cents, string $idempotencyKey): array
    {
        if (!self::validOpening($opening) || !self::uuid($idempotencyKey) || $cents < 1 || $cents > 100000) return $this->unavailable();
        return $this->payment('POST', '/openings/' . $opening['id'] . '/payments', $token,
            ['instance_id' => $opening['instance_id'], 'generation' => $opening['generation'], 'amount_cents' => $cents], $idempotencyKey, [200, 201], self::paymentOf($opening['id']));
    }

    /** Stato di una richiesta della sessione: Napay verifica la chain a ogni lettura. */
    public function viewPayment(string $token, array $opening, string $paymentId): array
    {
        if (!self::validOpening($opening) || !self::uuid($paymentId)) return $this->unavailable();
        return $this->payment('GET', '/payments/' . $paymentId, $token, null, null, [200], self::paymentOf($opening['id'], $paymentId));
    }

    public function cancelPayment(string $token, array $opening, string $paymentId): array
    {
        if (!self::validOpening($opening) || !self::uuid($paymentId)) return $this->unavailable();
        return $this->payment('POST', '/payments/' . $paymentId . '/cancel', $token,
            ['instance_id' => $opening['instance_id'], 'generation' => $opening['generation']], null, [200], self::paymentOf($opening['id'], $paymentId));
    }

    /** Storico del terminale dell'apertura corrente (Napay: ultimi 50, più recenti prima). */
    public function paymentHistory(string $token, array $opening): array
    {
        if (!self::validOpening($opening)) return $this->unavailable();
        return $this->payment('GET', '/openings/' . $opening['id'] . '/payments', $token, null, null, [200],
            static fn($data) => self::validHistory($data), 'payments');
    }

    /** Storico come restituito da Napay: solo la lista, voci con otto campi esatti, mai QR o destinatario. */
    public static function validHistory($data): bool
    {
        if (!is_array($data) || array_keys($data) !== ['payments'] || !is_array($data['payments']) || !array_is_list($data['payments']) || count($data['payments']) > 50) return false;
        foreach ($data['payments'] as $item) {
            if (!is_array($item) || array_keys($item) !== ['id', 'amount', 'currency', 'state', 'created_at', 'paid_at', 'tx_hash', 'own']) return false;
            $paid = $item['state'] === 'paid';
            if (!self::uuid($item['id']) || $item['currency'] !== 'TTS' || !is_string($item['amount']) || preg_match('/^(?:0|[1-9][0-9]{0,3})\.[0-9]{2}$/D', $item['amount']) !== 1
                || !in_array($item['state'], ['pending', 'paid', 'expired', 'cancelled'], true) || !is_int($item['created_at']) || $item['created_at'] < 1 || !is_bool($item['own'])
                || !($paid ? is_string($item['tx_hash']) && preg_match('/^0x[0-9a-f]{64}$/D', $item['tx_hash']) === 1 && is_int($item['paid_at'])
                    : $item['tx_hash'] === null && $item['paid_at'] === null)) return false;
        }
        return true;
    }

    /** Pagamento come restituito da Napay: dieci campi esatti, QR coerente con indirizzo e importo, esito coerente con lo stato. */
    public static function validPayment($payment): bool
    {
        if (!is_array($payment) || array_keys($payment) !== ['id', 'opening_id', 'amount', 'currency', 'state', 'to_address', 'expires_at', 'qr', 'tx_hash', 'paid_at']) return false;
        $paid = $payment['state'] === 'paid';
        return self::uuid($payment['id']) && self::uuid($payment['opening_id']) && $payment['currency'] === 'TTS'
            && is_string($payment['amount']) && preg_match('/^(?:0|[1-9][0-9]{0,3})\.[0-9]{2}$/D', $payment['amount']) === 1
            && in_array($payment['state'], ['pending', 'paid', 'expired', 'cancelled'], true)
            && is_string($payment['to_address']) && preg_match('/^0x[0-9a-fA-F]{40}$/D', $payment['to_address']) === 1
            && is_int($payment['expires_at']) && $payment['expires_at'] > 0
            && is_string($payment['qr']) && preg_match('/^' . preg_quote($payment['to_address'] . '&amount=' . $payment['amount'], '/') . '&message=napay:[0-9a-f]{32}$/D', $payment['qr']) === 1
            && ($paid ? is_string($payment['tx_hash']) && preg_match('/^0x[0-9a-f]{64}$/D', $payment['tx_hash']) === 1 && is_int($payment['paid_at'])
                : $payment['tx_hash'] === null && $payment['paid_at'] === null);
    }

    /** La risposta deve descrivere un pagamento dell'apertura corrente (e proprio quello richiesto). */
    private static function paymentOf(string $openingId, ?string $paymentId = null): \Closure
    {
        return static fn($data) => self::validPayment($data) && $data['opening_id'] === $openingId && ($paymentId === null || $data['id'] === $paymentId);
    }

    /** @param callable $valid controllo della risposta di successo; restituita sotto la chiave $field */
    private function payment(string $method, string $path, string $token, ?array $body, ?string $idempotencyKey, array $success, callable $valid, string $field = 'payment'): array
    {
        try {
            if ($this->baseUrl !== 'https://napay.local/api/pos/v1' || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D', $token)
                || !is_readable($this->caFile) || !is_readable($this->clientKeyFile)) return $this->unavailable();
            $key = @file_get_contents($this->clientKeyFile, false, null, 0, 256);
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', trim($key))) return $this->unavailable();
            $payload = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
            $response = $this->transport($this->baseUrl . $path, $method, $payload, trim($key), null, $token, $idempotencyKey);
            $status = $response['status'] ?? null;
            if (!empty($response['setCookie']) || strlen($response['body'] ?? '') > 16384) return $this->unavailable();
            if (!preg_match('~^application/json(?:\s*;|$)~i', $response['type'] ?? '')) return $this->unavailable();
            $data = json_decode($response['body'], true, 4, JSON_THROW_ON_ERROR);
            if (in_array($status, $success, true)) {
                if (!$valid($data)) return $this->unavailable();
                return ['ok' => true, $field => $field === 'payments' ? $data['payments'] : $data];
            }
            $code = is_array($data) ? ($data['error']['code'] ?? null) : null;
            $known = [401 => ['invalid_token'], 404 => ['not_found'], 409 => ['opening_invalid', 'idempotency_conflict', 'payment_not_pending'],
                400 => ['validation_failed'], 503 => ['payment_unavailable']];
            if ($status === 429 && $code === 'rate_limited') {
                $retry = filter_var($response['retry'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
                return ['ok' => false, 'error' => 'rate_limited', 'retry' => $retry === false ? 60 : $retry];
            }
            if (in_array($code, $known[$status] ?? [], true)) return ['ok' => false, 'error' => $code];
            return $this->unavailable();
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /** Apertura come restituita da Napay e custodita nella sessione POS: cinque campi esatti. */
    public static function validOpening($opening): bool
    {
        return is_array($opening) && array_keys($opening) === ['id', 'terminal_id', 'instance_id', 'generation', 'state']
            && self::uuid($opening['id']) && self::uuid($opening['terminal_id']) && self::uuid($opening['instance_id'])
            && is_string($opening['generation']) && preg_match('/^[1-9][0-9]{0,18}$/D', $opening['generation']) === 1 && $opening['state'] === 'active';
    }

    private function opening(string $method, string $path, string $token, ?array $body, ?string $idempotencyKey, array $success,
        ?string $terminalId = null, ?string $instanceId = null, ?array $expected = null): array
    {
        try {
            if ($this->baseUrl !== 'https://napay.local/api/pos/v1' || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D', $token)
                || !is_readable($this->caFile) || !is_readable($this->clientKeyFile)) return $this->unavailable();
            $key = @file_get_contents($this->clientKeyFile, false, null, 0, 256);
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', trim($key))) return $this->unavailable();
            $payload = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
            $response = $this->transport($this->baseUrl . $path, $method, $payload, trim($key), null, $token, $idempotencyKey);
            $status = $response['status'] ?? null;
            if (!empty($response['setCookie']) || strlen($response['body'] ?? '') > 16384) return $this->unavailable();
            if (in_array($status, $success, true) && $status === 204) return ($response['body'] ?? null) === '' ? ['ok' => true] : $this->unavailable();
            if (!preg_match('~^application/json(?:\s*;|$)~i', $response['type'] ?? '')) return $this->unavailable();
            $data = json_decode($response['body'], true, 4, JSON_THROW_ON_ERROR);
            if (in_array($status, $success, true)) {
                // La risposta deve descrivere proprio l'apertura richiesta o verificata.
                if (!self::validOpening($data) || $data['terminal_id'] !== $terminalId || $data['instance_id'] !== $instanceId
                    || ($expected !== null && $data !== $expected)) return $this->unavailable();
                return ['ok' => true, 'opening' => $data];
            }
            $code = is_array($data) ? ($data['error']['code'] ?? null) : null;
            $known = [401 => ['invalid_token'], 404 => ['not_found'], 409 => ['terminal_in_use', 'opening_invalid', 'idempotency_conflict'], 400 => ['validation_failed']];
            if ($status === 429 && $code === 'rate_limited') {
                $retry = filter_var($response['retry'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
                return ['ok' => false, 'error' => 'rate_limited', 'retry' => $retry === false ? 60 : $retry];
            }
            if (in_array($code, $known[$status] ?? [], true)) return ['ok' => false, 'error' => $code];
            return $this->unavailable();
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    private static function uuid($value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) === 1;
    }

    protected function transport(string $url, string $method, string $body, string $key, ?string $ip, ?string $token, ?string $idempotencyKey = null): array
    {
        $curl = curl_init($url);
        $raw = ''; $retry = null; $setCookie = false;
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Expect:', 'X-POS-Client-Key: ' . $key];
        if ($ip !== null) $headers[] = 'X-POS-Operator-IP: ' . $ip;
        if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
        if ($idempotencyKey !== null) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
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
