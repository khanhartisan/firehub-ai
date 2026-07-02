<?php

namespace App\Utils;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;

class Markdown
{
    protected static HtmlConverter $htmlConverter;

    protected static CommonMarkConverter $markdownConverter;

    public static function htmlToMarkdown(string $html): string
    {
        return static::getHtmlConverter()->convert($html);
    }

    /**
     * @throws CommonMarkException
     */
    public static function markdownToHtml(string $markdown): string
    {
        return static::getMarkdownConverter()->convert($markdown);
    }

    protected static function getHtmlConverter(): HtmlConverter
    {
        return static::$htmlConverter ??= (function () {
            $converter = new HtmlConverter([
                'strip_tags' => true,
                'header_style' => 'atx'
            ]);
            $converter->getEnvironment()->addConverter(new TableConverter());
            return $converter;
        })();
    }

    protected static function getMarkdownConverter(): MarkdownConverter
    {
        return static::$markdownConverter ??= (function () {
            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $converter->getEnvironment()->addExtension(new TableExtension());

            return $converter;
        })();
    }
}