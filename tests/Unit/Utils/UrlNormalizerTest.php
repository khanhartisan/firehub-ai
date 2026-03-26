<?php

namespace Tests\Unit\Utils;

use App\Utils\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    public static function canonicalPairsProvider(): array
    {
        return [
            'empty' => ['', ''],
            'trim only non-http' => ['  /relative  ', '/relative'],
            'scheme and host case' => ['HTTPS://Example.COM/', 'https://example.com/'],
            'collapse slashes trim trailing' => ['https://ex.com/foo//bar/', 'https://ex.com/foo/bar'],
            'strip fragment' => ['https://ex.com/page#section', 'https://ex.com/page'],
            'default http port' => ['http://ex.com:80/x', 'http://ex.com/x'],
            'default https port' => ['https://ex.com:443/', 'https://ex.com/'],
            'keep non-default port' => ['https://ex.com:8443/', 'https://ex.com:8443/'],
            'root path explicit' => ['https://ex.com', 'https://ex.com/'],
            'query preserved' => ['https://ex.com/a?x=1&y=2', 'https://ex.com/a?x=1&y=2'],
        ];
    }

    #[DataProvider('canonicalPairsProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, UrlNormalizer::normalize($input));
    }

    public static function toFullUrlProvider(): array
    {
        return [
            'empty relative' => ['https://example.com/foo', '', 'https://example.com/foo'],
            'empty base empty relative' => ['', '', ''],
            'empty base with relative' => ['', '/x', ''],
            'absolute path' => ['https://example.com/a/b', '/c', 'https://example.com/c'],
            'relative segment' => ['https://example.com/a/b', 'c', 'https://example.com/a/c'],
            'relative from file' => ['https://example.com/a/b.html', 'c.png', 'https://example.com/a/c.png'],
            'parent segments' => ['https://example.com/a/b/c', '../d', 'https://example.com/a/d'],
            'already absolute' => ['https://example.com/x', 'https://other.test/z', 'https://other.test/z'],
            'protocol-relative' => ['https://example.com/', '//other.test/x', 'https://other.test/x'],
            'query-only reference' => ['https://example.com/a?x=1', '?y=2', 'https://example.com/a?y=2'],
        ];
    }

    #[DataProvider('toFullUrlProvider')]
    public function test_to_full_url(string $base, string $relative, string $expected): void
    {
        $this->assertSame($expected, UrlNormalizer::toFullUrl($base, $relative));
    }
}
