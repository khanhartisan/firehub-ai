<?php

namespace Tests\Unit\Utils;

use App\Utils\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase
{
    public function test_html_to_markdown_converts_headers_to_atx_style(): void
    {
        $html = '<h2>Section Title</h2><p>Body text</p>';

        $result = Markdown::htmlToMarkdown($html);

        $this->assertStringContainsString('## Section Title', $result);
        $this->assertStringContainsString('Body text', $result);
    }

    public function test_html_to_markdown_converts_html_tables(): void
    {
        $html = '<table><thead><tr><th>Name</th><th>Age</th></tr></thead><tbody><tr><td>Alice</td><td>30</td></tr></tbody></table>';

        $result = Markdown::htmlToMarkdown($html);

        $this->assertStringContainsString('| Name | Age |', $result);
        $this->assertStringContainsString('|---|---|', $result);
        $this->assertStringContainsString('| Alice | 30 |', $result);
    }

    public function test_markdown_to_html_converts_markdown_tables(): void
    {
        $markdown = "| Name | Age |\n|---|---|\n| Alice | 30 |";

        $result = Markdown::markdownToHtml($markdown);

        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<th>Name</th>', $result);
        $this->assertStringContainsString('<th>Age</th>', $result);
        $this->assertStringContainsString('<td>Alice</td>', $result);
        $this->assertStringContainsString('<td>30</td>', $result);
    }

    public function test_markdown_to_html_strips_raw_html_input(): void
    {
        $markdown = "# Title\n\n<script>alert('xss')</script>\n\nParagraph";

        $result = Markdown::markdownToHtml($markdown);

        $this->assertStringContainsString('<h1>Title</h1>', $result);
        $this->assertStringContainsString('<p>Paragraph</p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert(\'xss\')', $result);
    }

    public function test_markdown_to_html_blocks_unsafe_links(): void
    {
        $markdown = '[Click me](javascript:alert(1))';

        $result = Markdown::markdownToHtml($markdown);

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('Click me', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('href=', $result);
    }
}
