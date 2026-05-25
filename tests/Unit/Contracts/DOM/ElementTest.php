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

        $html = $element->toHtml(true);
        $this->assertStringContainsString('<div ', $html);
        $this->assertStringContainsString('class="container"', $html);
        $this->assertStringContainsString('<p', $html);
        $this->assertStringContainsString('>Hello</p> world</div>', $html);
        $this->assertMatchesRegularExpression('/data-identifier="[^"]+"/', $html);
    }

    public function test_it_escapes_text_and_attribute_values_when_rendering_html(): void
    {
        $element = (new Element)
            ->setType(ElementType::A)
            ->setProp('title', '3 > 2 "quoted"')
            ->setProp('href', 'https://example.com/?q=a&b=c')
            ->addChild('Use "quotes" & tags <here>');

        $html = $element->toHtml(true);
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('title="3 &gt; 2 &quot;quoted&quot;"', $html);
        $this->assertStringContainsString('href="https://example.com/?q=a&amp;b=c"', $html);
        $this->assertStringContainsString('Use &quot;quotes&quot; &amp; tags &lt;here&gt;</a>', $html);
        $this->assertMatchesRegularExpression('/data-identifier="[^"]+"/', $html);
    }

    public function test_it_renders_fragment_when_type_is_missing(): void
    {
        $fragment = (new Element)
            ->setChildren([
                'A',
                (new Element)->setType(ElementType::STRONG)->addChild('B'),
            ]);

        $html = $fragment->toHtml(true);
        $this->assertStringContainsString('A<strong', $html);
        $this->assertStringContainsString('>B</strong>', $html);
        $this->assertMatchesRegularExpression('/<strong data-identifier="[^"]+">/', $html);
    }

    public function test_it_renders_void_elements_as_self_closing_tags(): void
    {
        $image = (new Element)
            ->setType(ElementType::IMG)
            ->setProps([
                'src' => 'https://example.com/image.jpg',
                'alt' => 'Hero image',
            ]);

        $html = $image->toHtml(true);
        $this->assertStringStartsWith('<img ', $html);
        $this->assertStringContainsString('src="https://example.com/image.jpg"', $html);
        $this->assertStringContainsString('alt="Hero image"', $html);
        $this->assertStringEndsWith(' />', $html);
        $this->assertMatchesRegularExpression('/data-identifier="[^"]+"/', $html);
    }

    public function test_it_returns_inner_html_of_children_only(): void
    {
        $element = (new Element)
            ->setType(ElementType::DIV)
            ->addChild('Hello ')
            ->addChild((new Element)->setType(ElementType::STRONG)->addChild('World'));

        $html = $element->getInnerHtml(true);
        $this->assertStringContainsString('Hello <strong ', $html);
        $this->assertStringContainsString('>World</strong>', $html);
        $this->assertMatchesRegularExpression('/<strong data-identifier="[^"]+">/', $html);
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

    public function test_it_inserts_after_a_deeply_nested_element(): void
    {
        $target = (new Element)->setType(ElementType::P)->addChild('Target');
        $sibling = (new Element)->setType(ElementType::P)->addChild('Sibling');
        $inserted = (new Element)->setType(ElementType::P)->addChild('Inserted');

        $inner = (new Element)->setType(ElementType::DIV)->setChildren([$target, $sibling]);
        $root = (new Element)->setType(ElementType::DIV)->addChild($inner);

        $root->insertAfter($target->getIdentifier(), $inserted);

        $innerChildren = $inner->getChildren();
        $this->assertCount(3, $innerChildren);
        $this->assertSame($target, $innerChildren[0]);
        $this->assertSame($inserted, $innerChildren[1]);
        $this->assertSame($sibling, $innerChildren[2]);
        $this->assertCount(1, $root->getChildren());
    }

    public function test_it_inserts_before_a_deeply_nested_element(): void
    {
        $target = (new Element)->setType(ElementType::P)->addChild('Target');
        $sibling = (new Element)->setType(ElementType::P)->addChild('Sibling');
        $inserted = (new Element)->setType(ElementType::P)->addChild('Inserted');

        $inner = (new Element)->setType(ElementType::DIV)->setChildren([$sibling, $target]);
        $root = (new Element)->setType(ElementType::DIV)->addChild($inner);

        $root->insertBefore($target->getIdentifier(), $inserted);

        $innerChildren = $inner->getChildren();
        $this->assertCount(3, $innerChildren);
        $this->assertSame($sibling, $innerChildren[0]);
        $this->assertSame($inserted, $innerChildren[1]);
        $this->assertSame($target, $innerChildren[2]);
        $this->assertCount(1, $root->getChildren());
    }

    public function test_it_inserts_relative_to_identifier_at_arbitrary_depth(): void
    {
        $target = (new Element)->setType(ElementType::P)->addChild('Deep target');
        $inserted = (new Element)->setType(ElementType::SPAN)->addChild('Inserted');

        $level3 = (new Element)->setType(ElementType::DIV)->addChild($target);
        $level2 = (new Element)->setType(ElementType::DIV)->addChild($level3);
        $level1 = (new Element)->setType(ElementType::DIV)->addChild($level2);

        $level1->insertAfter($target->getIdentifier(), $inserted);

        $deepChildren = $level3->getChildren();
        $this->assertCount(2, $deepChildren);
        $this->assertSame($target, $deepChildren[0]);
        $this->assertSame($inserted, $deepChildren[1]);
    }

    public function test_it_throws_when_identifier_is_absent_from_entire_tree(): void
    {
        $inner = (new Element)->setType(ElementType::DIV)
            ->addChild((new Element)->setType(ElementType::P)->addChild('Nested'));

        $root = (new Element)->setType(ElementType::DIV)->addChild($inner);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Child with the provided identifier was not found.');

        $root->insertAfter('missing-identifier', 'insert me');
    }

    public function test_it_builds_element_tree_from_single_root_html(): void
    {
        $element = Element::fromHtml(
            '<div data-identifier="root-1" class="wrapper"><p data-identifier="p-1">Hello <strong>world</strong></p></div>'
        );

        $this->assertSame(ElementType::DIV, $element->getType());
        $this->assertSame('root1', $element->getIdentifier());
        $this->assertSame('wrapper', $element->getProps()['class'] ?? null);

        $children = $element->getChildren();
        $this->assertCount(1, $children);
        $this->assertInstanceOf(Element::class, $children[0]);

        /** @var Element $paragraph */
        $paragraph = $children[0];
        $this->assertSame(ElementType::P, $paragraph->getType());
        $this->assertSame('p-1', $paragraph->getIdentifier());

        $paragraphChildren = $paragraph->getChildren();
        $this->assertCount(2, $paragraphChildren);
        $this->assertSame('Hello ', $paragraphChildren[0]);
        $this->assertInstanceOf(Element::class, $paragraphChildren[1]);
        $this->assertSame(ElementType::STRONG, $paragraphChildren[1]->getType());
        $this->assertSame('world', $paragraphChildren[1]->getChildren()[0]);
    }

    public function test_it_builds_fragment_children_when_html_has_multiple_roots(): void
    {
        $fragment = Element::fromHtml('A<span class="x">B</span><br />C');

        $this->assertNull($fragment->getType());
        $children = $fragment->getChildren();

        $this->assertCount(4, $children);
        $this->assertSame('A', $children[0]);
        $this->assertInstanceOf(Element::class, $children[1]);
        $this->assertSame(ElementType::SPAN, $children[1]->getType());
        $this->assertSame('x', $children[1]->getProps()['class'] ?? null);
        $this->assertSame('B', $children[1]->getChildren()[0]);
        $this->assertInstanceOf(Element::class, $children[2]);
        $this->assertSame(ElementType::BR, $children[2]->getType());
        $this->assertSame('C', $children[3]);
    }

    public function test_it_roundtrips_html_with_escaping_and_identifiers(): void
    {
        $html = '<a data-identifier="a-1" href="https://example.com/?q=a&amp;b=c">Use &quot;quotes&quot; &amp; tags &lt;here&gt;</a>';

        $element = Element::fromHtml($html);
        $rendered = $element->toHtml(true);

        $this->assertStringContainsString('data-identifier="a-1"', $rendered);
        $this->assertStringContainsString('href="https://example.com/?q=a&amp;b=c"', $rendered);
        $this->assertStringContainsString('Use &quot;quotes&quot; &amp; tags &lt;here&gt;', $rendered);
        $this->assertStringStartsWith('<a ', $rendered);
        $this->assertStringEndsWith('</a>', $rendered);
    }

    public function test_it_ignores_unknown_tags_not_in_element_type_enum(): void
    {
        $element = Element::fromHtml('<section data-identifier="sec-1" class="hero">Lead <em>text</em></section>');

        $this->assertNull($element->getType());
        $this->assertSame('sec1', $element->getIdentifier());
        $this->assertSame('hero', $element->getProps()['class'] ?? null);

        $children = $element->getChildren();
        $this->assertCount(2, $children);
        $this->assertSame('Lead ', $children[0]);
        $this->assertInstanceOf(Element::class, $children[1]);
        $this->assertSame(ElementType::EM, $children[1]->getType());
        $this->assertSame('text', $children[1]->getChildren()[0]);
    }
}
