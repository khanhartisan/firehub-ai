<?php

namespace App\Utils;

/**
 * Canonical HTTP(S) form for entity URLs so url_hash dedupes equivalent addresses.
 */
final class EntityUrlNormalizer
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! preg_match('#\Ahttps?://#i', $url)) {
            return $url;
        }

        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host']) || $parsed['host'] === '') {
            return $url;
        }

        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return $url;
        }

        $host = strtolower($parsed['host']);

        $port = $parsed['port'] ?? null;
        if ($port !== null) {
            if ($scheme === 'http' && (int) $port === 80) {
                $port = null;
            } elseif ($scheme === 'https' && (int) $port === 443) {
                $port = null;
            }
        }

        $path = $parsed['path'] ?? '';
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        if ($path === '' || $path === '/') {
            $path = '/';
        } else {
            $path = rtrim($path, '/');
        }

        $query = $parsed['query'] ?? null;
        $queryString = '';
        if ($query !== null && $query !== '') {
            $queryString = '?'.$query;
        }

        $authority = $host;
        if ($port !== null) {
            $authority .= ':'.$port;
        }

        $userInfo = '';
        if (isset($parsed['user']) && $parsed['user'] !== '') {
            $userInfo = $parsed['user'];
            if (isset($parsed['pass'])) {
                $userInfo .= ':'.$parsed['pass'];
            }
            $userInfo .= '@';
        }

        return $scheme.'://'.$userInfo.$authority.$path.$queryString;
    }
}
