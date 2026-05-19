<?php

namespace Tests\Unit\Contracts\DOM;

use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    public function test_it_parses_headings_and_paragraphs_from_markdown(): void
    {
        $markdown = <<<'MD'
## Introduction

This is a short intro paragraph.
MD;

        $article = Article::fromMarkdown($markdown);

        $this->assertSame(ElementType::ARTICLE, $article->getType());
        $this->assertCount(2, $article->getChildren());

        $heading = $article->getChildren()[0];
        $this->assertInstanceOf(Element::class, $heading);
        $this->assertSame(ElementType::H2, $heading->getType());
        $this->assertSame(['Introduction'], $heading->getChildren());

        $paragraph = $article->getChildren()[1];
        $this->assertInstanceOf(Element::class, $paragraph);
        $this->assertSame(ElementType::P, $paragraph->getType());
        $this->assertSame(['This is a short intro paragraph.'], $paragraph->getChildren());
    }

    public function test_it_preserves_paragraph_wrapper_for_single_block_markdown(): void
    {
        $article = Article::fromMarkdown('Hello world');

        $this->assertSame(ElementType::ARTICLE, $article->getType());
        $this->assertCount(1, $article->getChildren());

        $paragraph = $article->getChildren()[0];
        $this->assertInstanceOf(Element::class, $paragraph);
        $this->assertSame(ElementType::P, $paragraph->getType());
        $this->assertSame(['Hello world'], $paragraph->getChildren());
    }

    public function test_it_parses_inline_formatting_and_links_from_markdown(): void
    {
        $markdown = 'This is a **bold** statement with a [link](https://example.com).';

        $article = Article::fromMarkdown($markdown);

        $paragraph = $article->getChildren()[0];
        $this->assertInstanceOf(Element::class, $paragraph);
        $this->assertSame(ElementType::P, $paragraph->getType());

        $children = $paragraph->getChildren();
        $this->assertCount(5, $children);
        $this->assertSame('This is a ', $children[0]);
        $this->assertInstanceOf(Element::class, $children[1]);
        $this->assertSame(ElementType::STRONG, $children[1]->getType());
        $this->assertSame(['bold'], $children[1]->getChildren());
        $this->assertSame(' statement with a ', $children[2]);
        $this->assertInstanceOf(Element::class, $children[3]);
        $this->assertSame(ElementType::A, $children[3]->getType());
        $this->assertSame('https://example.com', $children[3]->getProps()['href'] ?? null);
        $this->assertSame(['link'], $children[3]->getChildren());
        $this->assertSame('.', $children[4]);
    }

    public function test_it_parses_unordered_lists_from_markdown(): void
    {
        $markdown = <<<'MD'
- First item
- Second item
MD;

        $article = Article::fromMarkdown($markdown);

        $list = $article->getChildren()[0];
        $this->assertInstanceOf(Element::class, $list);
        $this->assertSame(ElementType::UL, $list->getType());
        $this->assertCount(2, $list->getChildren());

        $firstItem = $list->getChildren()[0];
        $this->assertInstanceOf(Element::class, $firstItem);
        $this->assertSame(ElementType::LI, $firstItem->getType());
        $this->assertSame(['First item'], $firstItem->getChildren());

        $secondItem = $list->getChildren()[1];
        $this->assertInstanceOf(Element::class, $secondItem);
        $this->assertSame(ElementType::LI, $secondItem->getType());
        $this->assertSame(['Second item'], $secondItem->getChildren());
    }

    public function test_it_parses_fenced_code_blocks_from_markdown(): void
    {
        $markdown = <<<'MD'
```php
echo "hello";
```
MD;

        $article = Article::fromMarkdown($markdown);

        $pre = $article->getChildren()[0];
        $this->assertInstanceOf(Element::class, $pre);
        $this->assertSame(ElementType::PRE, $pre->getType());

        $code = $pre->getChildren()[0];
        $this->assertInstanceOf(Element::class, $code);
        $this->assertSame(ElementType::CODE, $code->getType());
        $this->assertSame('language-php', $code->getProps()['class'] ?? null);
        $this->assertSame(["echo \"hello\";\n"], $code->getChildren());
    }

    public function test_it_drops_whitespace_only_text_nodes_between_block_elements(): void
    {
        $article = Article::fromMarkdown("## Heading\n\nParagraph text.");

        foreach ($article->getChildren() as $child) {
            $this->assertInstanceOf(Element::class, $child);
        }
    }

    public function test_it_roundtrips_article_structure_through_markdown(): void
    {
        $original = (new Article)
            ->setChildren([
                (new Element)->setType(ElementType::H2)->addChild('Introduction'),
                (new Element)->setType(ElementType::P)->addChild('This is a short intro paragraph.'),
            ]);

        $restored = Article::fromMarkdown($original->toMarkdown());

        $this->assertSame(
            $this->stripIdentifiers($original->toArray()),
            $this->stripIdentifiers($restored->toArray()),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripIdentifiers(array $data): array
    {
        unset($data['identifier']);

        if (! isset($data['children']) || ! is_array($data['children'])) {
            return $data;
        }

        $data['children'] = array_map(
            fn (array|string $child): array|string => is_array($child)
                ? $this->stripIdentifiers($child)
                : $child,
            $data['children'],
        );

        return $data;
    }
}
