<?php

namespace Tests\Unit\Contracts\DOM;

use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use Exception;
use Tests\TestCase;

class ElementTest extends TestCase
{
    public function test_it_serializes_and_restores_nested_elements(): void
    {
        $root = (new Element)
            ->setType(ElementType::DIV)
            ->setProps(['class' => 'article'])
            ->setChildren([
                (new Element)
                    ->setType(ElementType::P)
                    ->setChildren(['Hello world']),
                'Tail text',
            ]);

        $data = $root->toArray();
        $restored = Element::fromArray($data);

        $this->assertSame('div', $data['type']);
        $this->assertSame(['class' => 'article'], $data['props']);
        $this->assertCount(2, $restored->getChildren());
        $this->assertInstanceOf(Element::class, $restored->getChildren()[0]);
        $this->assertSame('Tail text', $restored->getChildren()[1]);
    }

    public function test_it_renders_html_with_nested_children_and_attributes(): void
    {
        $element = (new Element)
            ->setType(ElementType::DIV)
            ->setProps(['class' => 'container'])
            ->addChild(
                (new Element)
                    ->setType(ElementType::P)
                    ->addChild('Hello')
            )
            ->addChild(' world');

        $this->assertSame('<div class="container"><p>Hello</p> world</div>', $element->toHtml());
    }

    public function test_it_escapes_text_and_attribute_values_when_rendering_html(): void
    {
        $element = (new Element)
            ->setType(ElementType::A)
            ->setProp('title', '3 > 2 "quoted"')
            ->setProp('href', 'https://example.com/?q=a&b=c')
            ->addChild('Use "quotes" & tags <here>');

        $this->assertSame(
            '<a title="3 &gt; 2 &quot;quoted&quot;" href="https://example.com/?q=a&amp;b=c">Use &quot;quotes&quot; &amp; tags &lt;here&gt;</a>',
            $element->toHtml()
        );
    }

    public function test_it_renders_fragment_when_type_is_missing(): void
    {
        $fragment = (new Element)
            ->setChildren([
                'A',
                (new Element)->setType(ElementType::STRONG)->addChild('B'),
            ]);

        $this->assertSame('A<strong>B</strong>', $fragment->toHtml());
    }

    public function test_it_renders_void_elements_as_self_closing_tags(): void
    {
        $image = (new Element)
            ->setType(ElementType::IMG)
            ->setProps([
                'src' => 'https://example.com/image.jpg',
                'alt' => 'Hero image',
            ]);

        $this->assertSame(
            '<img src="https://example.com/image.jpg" alt="Hero image" />',
            $image->toHtml()
        );
    }

    public function test_it_returns_inner_html_of_children_only(): void
    {
        $element = (new Element)
            ->setType(ElementType::DIV)
            ->addChild('Hello ')
            ->addChild((new Element)->setType(ElementType::STRONG)->addChild('World'));

        $this->assertSame('Hello <strong>World</strong>', $element->getInnerHtml());
    }

    public function test_it_returns_escaped_inner_html_for_text_nodes(): void
    {
        $element = (new Element)
            ->setType(ElementType::P)
            ->addChild('5 > 3 & "safe"');

        $this->assertSame('5 &gt; 3 &amp; &quot;safe&quot;', $element->getInnerHtml());
    }

    public function test_it_inserts_child_after_matching_identifier(): void
    {
        $firstChild = (new Element)->setType(ElementType::P)->addChild('First');
        $secondChild = (new Element)->setType(ElementType::P)->addChild('Second');
        $insertedChild = (new Element)->setType(ElementType::P)->addChild('Inserted');

        $parent = (new Element)
            ->setType(ElementType::DIV)
            ->setChildren([$firstChild, $secondChild]);

        $parent->insertAfter($firstChild->getIdentifier(), $insertedChild);

        $children = $parent->getChildren();
        $this->assertCount(3, $children);
        $this->assertSame($firstChild, $children[0]);
        $this->assertSame($insertedChild, $children[1]);
        $this->assertSame($secondChild, $children[2]);
    }

    public function test_it_throws_when_inserting_after_unknown_identifier(): void
    {
        $parent = (new Element)
            ->setType(ElementType::DIV)
            ->addChild((new Element)->setType(ElementType::P)->addChild('Only child'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Child with the provided identifier was not found.');

        $parent->insertAfter('missing-identifier', 'insert me');
    }

    public function test_it_inserts_string_child_after_matching_identifier_in_mixed_children(): void
    {
        $firstChild = (new Element)->setType(ElementType::P)->addChild('First');
        $secondChild = (new Element)->setType(ElementType::P)->addChild('Second');

        $parent = (new Element)
            ->setType(ElementType::DIV)
            ->setChildren([$firstChild, 'between', $secondChild]);

        $parent->insertAfter($firstChild->getIdentifier(), 'inserted-text');

        $children = $parent->getChildren();
        $this->assertCount(4, $children);
        $this->assertSame($firstChild, $children[0]);
        $this->assertSame('inserted-text', $children[1]);
        $this->assertSame('between', $children[2]);
        $this->assertSame($secondChild, $children[3]);
    }

    public function test_it_inserts_child_before_matching_identifier(): void
    {
        $firstChild = (new Element)->setType(ElementType::P)->addChild('First');
        $secondChild = (new Element)->setType(ElementType::P)->addChild('Second');
        $insertedChild = (new Element)->setType(ElementType::P)->addChild('Inserted');

        $parent = (new Element)
            ->setType(ElementType::DIV)
            ->setChildren([$firstChild, $secondChild]);

        $parent->insertBefore($secondChild->getIdentifier(), $insertedChild);

        $children = $parent->getChildren();
        $this->assertCount(3, $children);
        $this->assertSame($firstChild, $children[0]);
        $this->assertSame($insertedChild, $children[1]);
        $this->assertSame($secondChild, $children[2]);
    }

    public function test_it_throws_when_inserting_before_unknown_identifier(): void
    {
        $parent = (new Element)
            ->setType(ElementType::DIV)
            ->addChild((new Element)->setType(ElementType::P)->addChild('Only child'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Child with the provided identifier was not found.');

        $parent->insertBefore('missing-identifier', 'insert me');
    }

    public function test_it_inserts_string_child_before_first_matching_identifier_in_mixed_children(): void
    {
        $firstChild = (new Element)->setType(ElementType::P)->addChild('First');
        $secondChild = (new Element)->setType(ElementType::P)->addChild('Second');

        $parent = (new Element)
            ->setType(ElementType::DIV)
            ->setChildren(['leading-text', $firstChild, $secondChild]);

        $parent->insertBefore($firstChild->getIdentifier(), 'inserted-text');

        $children = $parent->getChildren();
        $this->assertCount(4, $children);
        $this->assertSame('leading-text', $children[0]);
        $this->assertSame('inserted-text', $children[1]);
        $this->assertSame($firstChild, $children[2]);
        $this->assertSame($secondChild, $children[3]);
    }
}
