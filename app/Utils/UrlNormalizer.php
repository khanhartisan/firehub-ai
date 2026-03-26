<?php

namespace App\Utils;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/**
 * Canonical HTTP(S) form for entity URLs so url_hash dedupes equivalent addresses.
 */
final class UrlNormalizer
{
    /**
     * Resolve a relative or absolute reference against a base URL (RFC 3986), then {@see normalize}.
     *
     * If the reference is already an http(s) URL, it is normalized directly.
     */
    public static function toFullUrl(string $baseUrl, string $relativePath): string
    {
        $baseUrl = trim($baseUrl);
        $relativePath = trim($relativePath);

        if ($relativePath === '') {
            return $baseUrl !== '' ? self::normalize($baseUrl) : '';
        }

        if (preg_match('#\Ahttps?://#i', $relativePath)) {
            return self::normalize($relativePath);
        }

        if ($baseUrl === '') {
            return '';
        }

        $resolved = (string) UriResolver::resolve(new Uri($baseUrl), new Uri($relativePath));

        return self::normalize($resolved);
    }

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
