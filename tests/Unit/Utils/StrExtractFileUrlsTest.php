<?php

namespace Tests\Unit\Utils;

use App\Utils\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StrExtractFileUrlsTest extends TestCase
{
    public static function mixedHtmlMarkdownProvider(): array
    {
        return [
            'markdown image and link' => [
                'See [home](https://example.com) and ![pic](https://cdn.example.com/a.png) and [doc](https://cdn.example.com/b.pdf).',
                ['https://cdn.example.com/a.png', 'https://cdn.example.com/b.pdf'],
            ],
            'markdown image optional title' => [
                '![hero](https://x.com/h.png "title")',
                ['https://x.com/h.png'],
            ],
            'markdown angle destination' => [
                '![](<https://x.com/z.webp>)',
                ['https://x.com/z.webp'],
            ],
            'reference definition' => [
                "![alt][r]\n[r]: https://y.com/out.png\n",
                ['https://y.com/out.png'],
            ],
            'embedded html in markdown' => [
                "Text\n\n<img src=\"https://h.com/i.jpg\">\n\n![md](https://h.com/m.png)\n",
                ['https://h.com/i.jpg', 'https://h.com/m.png'],
            ],
            'minified img src no quotes' => [
                '<img class=x src=https://c.com/n.webp>',
                ['https://c.com/n.webp'],
            ],
            'html video and ignore page anchor' => [
                '<video src="https://v.com/m.mp4"></video><a href="https://v.com/">home</a>',
                ['https://v.com/m.mp4'],
            ],
            'href to pdf only' => [
                '<a href="/static/a.pdf">x</a><a href="https://x.com/">home</a>',
                ['/static/a.pdf'],
            ],
            'mailto and javascript are ignored' => [
                '[e](mailto:a@b.com) [x](javascript:void(0)) ![i](https://ok.com/x.png)',
                ['https://ok.com/x.png'],
            ],
            'data uri ignored' => [
                '![x](data:image/png;base64,abc)',
                [],
            ],
            'duplicate url appears once' => [
                '![a](https://dup.com/f.pdf) ![b](https://dup.com/f.pdf)',
                ['https://dup.com/f.pdf'],
            ],
            'srcset picks first candidate per descriptor' => [
                '<img srcset="https://cdn.example.com/a.webp 1x, https://cdn.example.com/b.webp 2x" alt="x">',
                ['https://cdn.example.com/a.webp', 'https://cdn.example.com/b.webp'],
            ],
            'bare https urls with file extension' => [
                'See https://files.example.com/readme.txt and https://example.com/page.',
                ['https://files.example.com/readme.txt'],
            ],
            'hash anchor only is ignored' => [
                '[jump](#section) ![](https://z.com/z.jpg)',
                ['https://z.com/z.jpg'],
            ],
        ];
    }

    #[DataProvider('mixedHtmlMarkdownProvider')]
    public function test_extract_file_urls_mixed_content(string $content, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, Str::extractFileUrls($content));
    }
}
