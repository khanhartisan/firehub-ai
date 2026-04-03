<?php

namespace App\Utils;

class Str extends \Illuminate\Support\Str
{
    /** @var list<string> */
    private const FILE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif', 'tif', 'tiff',
        'mp4', 'webm', 'ogv', 'mov', 'm4v', 'mkv',
        'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'opus',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'zip', 'rar', '7z', 'tar', 'gz',
        'csv', 'txt', 'rtf',
    ];

    /**
     * Extract direct file URLs from HTML, Markdown, or mixed content (e.g. Markdown with embedded HTML).
     * Page links are omitted unless the target path has a known file extension, or the URL appears in a
     * media context (markdown image, img/video/audio/source/embed, etc.).
     */
    public static function extractFileUrls(string $content): array
    {
        $seen = [];
        $urls = [];

        $push = function (string $raw, bool $trustAsMedia) use (&$seen, &$urls): void {
            $url = self::normalizeUrlCandidate($raw);
            if ($url === null) {
                return;
            }
            if (! $trustAsMedia && ! self::pathHasRecognizedFileExtension($url)) {
                return;
            }
            if (isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $urls[] = $url;
        };

        // Markdown images — CommonMark allows optional title and <wrapped> destinations.
        if (preg_match_all('/!\[[^\]]*\]\(\s*(.*?)\s*\)/us', $content, $m)) {
            foreach ($m[1] as $raw) {
                $push(self::urlFromMarkdownParenInner($raw), true);
            }
        }

        // HTML media and embeds (quoted attributes).
        $htmlQuotedPatterns = [
            '/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<video\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<video\b[^>]*\bposter\s*=\s*["\']([^"\']+)["\']/i',
            '/<audio\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<source\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<embed\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<object\b[^>]*\bdata\s*=\s*["\']([^"\']+)["\']/i',
        ];
        foreach ($htmlQuotedPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $m)) {
                foreach ($m[1] as $raw) {
                    $push($raw, true);
                }
            }
        }

        // Minified / unquoted src on media tags (still file context).
        $htmlUnquotedSrcPatterns = [
            '/<img\b[^>]*\bsrc\s*=\s*([^\s>]+)/i',
            '/<video\b[^>]*\bsrc\s*=\s*([^\s>]+)/i',
            '/<video\b[^>]*\bposter\s*=\s*([^\s>]+)/i',
            '/<audio\b[^>]*\bsrc\s*=\s*([^\s>]+)/i',
            '/<source\b[^>]*\bsrc\s*=\s*([^\s>]+)/i',
            '/<embed\b[^>]*\bsrc\s*=\s*([^\s>]+)/i',
            '/<object\b[^>]*\bdata\s*=\s*([^\s>]+)/i',
        ];
        foreach ($htmlUnquotedSrcPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $m)) {
                foreach ($m[1] as $raw) {
                    $push($raw, true);
                }
            }
        }

        // srcset="url1 1x, url2 2x"
        if (preg_match_all('/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $srcset) {
                foreach (preg_split('/\s*,\s*/', $srcset) as $part) {
                    $candidate = trim(explode(' ', trim($part), 2)[0] ?? '');
                    if ($candidate !== '') {
                        $push($candidate, true);
                    }
                }
            }
        }

        // <link href="..."> (preload, icons, etc.) — extension filter only.
        if (preg_match_all('/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $raw) {
                $push($raw, false);
            }
        }

        // HTML anchors — quoted href; only file-like targets.
        if (preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $content, $m)) {
            foreach ($m[1] as $raw) {
                $push($raw, false);
            }
        }

        // <a href=...> without quotes — do not trust (often same-host pages).
        if (preg_match_all('/<a\b[^>]*\bhref\s*=\s*([^\s>]+)/i', $content, $m)) {
            foreach ($m[1] as $raw) {
                $push($raw, false);
            }
        }

        // Markdown links [text](url) — optional title; not images.
        if (preg_match_all('/(?<!\!)\[[^\]]*\]\(\s*(.*?)\s*\)/us', $content, $m)) {
            foreach ($m[1] as $raw) {
                $push(self::urlFromMarkdownParenInner($raw), false);
            }
        }

        // Reference-style definitions: [label]: https://... (used by ![alt][label] etc.)
        if (preg_match_all('/^\[[^\]]+\]:\s*(\S+)/mi', $content, $m)) {
            foreach ($m[1] as $raw) {
                $push($raw, false);
            }
        }

        // Bare http(s) URLs with a file extension (plain text, autolinks, loose HTML text).
        if (preg_match_all('/https?:\/\/[^\s<>"\'\])]+/iu', $content, $m)) {
            foreach ($m[0] as $raw) {
                $push(rtrim($raw, '.,;:!?)'), false);
            }
        }

        return $urls;
    }

    private static function urlFromMarkdownParenInner(string $inner): string
    {
        $inner = trim($inner);
        if ($inner === '') {
            return '';
        }
        if (str_starts_with($inner, '<')) {
            $end = strpos($inner, '>');
            if ($end !== false) {
                $inner = substr($inner, 1, $end - 1);
            }
        }
        $inner = trim($inner);

        return trim(preg_replace('/\s+["\'].*$/s', '', $inner) ?? $inner);
    }

    private static function normalizeUrlCandidate(string $raw): ?string
    {
        $url = trim($raw);
        if ($url === '') {
            return null;
        }
        $url = trim($url, "\"'");
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '<') && str_ends_with($url, '>')) {
            $url = substr($url, 1, -1);
            $url = trim($url);
        }
        $lower = strtolower($url);
        foreach (['data:', 'javascript:', 'mailto:', 'tel:', 'about:', 'blob:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return null;
            }
        }
        if ($url === '#' || str_starts_with($url, '#')) {
            return null;
        }

        return $url;
    }

    private static function pathHasRecognizedFileExtension(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            if (! str_contains($url, '://')) {
                $path = $url;
            } else {
                return false;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::FILE_EXTENSIONS, true);
    }
}
