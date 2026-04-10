<?php

namespace Tests\Unit\Utils;

use App\Utils\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StrSanitizeKeywordTest extends TestCase
{
    public static function sanitizeKeywordProvider(): array
    {
        return [
            'empty string' => ['', ''],
            'only ascii spaces' => ['   ', ''],
            'trim and collapse spaces' => ['  foo   bar  ', 'foo bar'],
            'tabs and newlines collapsed' => ["foo\t\n\r\n  bar", 'foo bar'],
            'unicode spaces collapsed' => ["foo\u{00A0}\u{2003}bar", 'foo bar'],
            'strip utf-8 bom prefix' => ["\xEF\xBB\xBFkeyword", 'keyword'],
            'bom plus surrounding space' => ["\xEF\xBB\xBF  x  ", 'x'],
            'ascii lowercase' => ['HELLO', 'hello'],
            'accented latin uppercase' => ['ÉCRIVAIN', 'écrivain'],
            'german sharp s' => ['STRASSE', 'strasse'],
            'greek uppercase' => ['ΈΛΛΗΝΙΚΆ', 'έλληνικά'],
            'cyrillic uppercase' => ['МОСКВА', 'москва'],
            'japanese hiragana unchanged' => ['こんにちは', 'こんにちは'],
            'japanese kanji mixed' => ['東京 旅行', '東京 旅行'],
            'japanese halfwidth katakana' => ['ｱｲｳ', 'ｱｲｳ'],
            'simplified chinese' => ['搜索引擎 优化', '搜索引擎 优化'],
            'traditional chinese' => ['繁體中文', '繁體中文'],
            'korean hangul' => ['한글 검색', '한글 검색'],
            'arabic letters' => ['مرحبا بالعالم', 'مرحبا بالعالم'],
            'vietnamese tones' => ['Tiếng Việt', 'tiếng việt'],
            'thai script' => ['สวัสดี', 'สวัสดี'],
            'hebrew' => ['שלום עולם', 'שלום עולם'],
            'polish' => ['ŁÓDŹ', 'łódź'],
            'icelandic eth' => ['ÐÆ', 'ðæ'],
            'removes null bytes' => ["a\x00b", 'ab'],
            'vertical tab collapses like whitespace' => ["a\x0Bb", 'a b'],
            'removes zero-width space' => ["a\u{200B}b", 'ab'],
            'emoji preserved' => ['Hello 😀 world', 'hello 😀 world'],
            'invalid utf-8 is scrubbed to replacement chars' => ["caf\xC0\xC0\xe9", 'caf???'],
        ];
    }

    #[DataProvider('sanitizeKeywordProvider')]
    public function test_sanitize_keyword(string $input, string $expected): void
    {
        $this->assertSame($expected, Str::sanitizeKeyword($input));
    }

    public function test_sanitize_keyword_does_not_merge_words_across_newlines(): void
    {
        $this->assertSame('hello world', Str::sanitizeKeyword("hello\nworld"));
    }

    public function test_sanitize_keyword_preserves_combining_characters_in_nfkc_sensitive_scripts(): void
    {
        // Combining acute on e (two code points) should remain a valid letter.
        $input = "e\u{0301}"; // e + combining acute
        $out = Str::sanitizeKeyword($input);
        $this->assertStringContainsString('e', $out);
        $this->assertSame(mb_strtolower($input, 'UTF-8'), $out);
    }
}
