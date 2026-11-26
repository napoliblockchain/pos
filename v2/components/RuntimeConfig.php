<?php
namespace app\components;

/** Operator-controlled settings only; never accepts backend origins from browser input. */
final class RuntimeConfig
{
    public const LOCAL_API = 'https://napay.local/api/pos/v1';

    public static function apiUrl($url, bool $production): bool
    {
        if (!is_string($url) || strlen($url) > 512 || (!$production && $url !== self::LOCAL_API)) return false;
        try { $parts = parse_url($url); } catch (\Throwable) { return false; }
        return is_array($parts) && array_diff(array_keys($parts), ['scheme', 'host', 'port', 'path']) === []
            && ($parts['scheme'] ?? null) === 'https' && ($parts['path'] ?? null) === '/api/pos/v1'
            && preg_match('/^[A-Za-z0-9.-]+$/D', $parts['host'] ?? '') === 1
            && filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            && (!isset($parts['port']) || ($parts['port'] >= 1 && $parts['port'] <= 65535));
    }

    /** Docker gateway IPv4 CIDRs; an all-addresses trust policy is deliberately forbidden. */
    public static function proxies(string $value): array
    {
        if (strlen($value) > 1024) throw new \RuntimeException('Invalid trusted proxy configuration.');
        $items = explode(',', $value);
        if (count($items) > 16) throw new \RuntimeException('Invalid trusted proxy configuration.');
        $result = [];
        foreach ($items as $item) {
            if (!preg_match('~^([0-9.]+)/([1-9]|[12][0-9]|3[0-2])$~D', $item, $match)
                || filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) throw new \RuntimeException('Invalid trusted proxy configuration.');
            $result[$item] = ['X-Forwarded-For', 'X-Forwarded-Proto'];
        }
        return $result;
    }
}
