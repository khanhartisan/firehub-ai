<?php

namespace Tests\Unit\Utils;

use App\Utils\EntityUrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntityUrlNormalizerTest extends TestCase
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
        $this->assertSame($expected, EntityUrlNormalizer::normalize($input));
    }
}
