<?php

namespace Tests\Unit\Contracts\VerticalResolver;

use App\Contracts\VerticalResolver\Vertical;
use InvalidArgumentException;
use Tests\TestCase;

class VerticalTest extends TestCase
{
    public function test_to_array_includes_identifier_name_description_and_children(): void
    {
        $vertical = new Vertical('News', 'News articles');
        $vertical->setIdentifier('news');

        $arr = $vertical->toArray();

        $this->assertSame('news', $arr['identifier']);
        $this->assertSame('News', $arr['name']);
        $this->assertSame('News articles', $arr['description']);
        $this->assertSame([], $arr['children']);
    }

    public function test_to_array_serializes_children_recursively(): void
    {
        $child = new Vertical('Tech', 'Tech news');
        $child->setIdentifier('tech');
        $parent = new Vertical('News', 'News');
        $parent->setIdentifier('news');
        $parent->addChild($child);

        $arr = $parent->toArray();

        $this->assertCount(1, $arr['children']);
        $this->assertSame('tech', $arr['children'][0]['identifier']);
        $this->assertSame('Tech', $arr['children'][0]['name']);
    }

    public function test_from_array_creates_vertical_with_name_and_description(): void
    {
        $vertical = Vertical::fromArray([
            'name' => 'Docs',
            'description' => 'Documentation',
        ]);

        $this->assertSame('Docs', $vertical->getName());
        $this->assertSame('Documentation', $vertical->getDescription());
        $this->assertNull($vertical->getIdentifier());
    }

    public function test_from_array_restores_identifier_and_children(): void
    {
        $vertical = Vertical::fromArray([
            'identifier' => 'docs',
            'name' => 'Docs',
            'description' => 'Documentation',
            'children' => [
                ['name' => 'API', 'description' => 'API docs'],
            ],
        ]);

        $this->assertSame('docs', $vertical->getIdentifier());
        $this->assertCount(1, $vertical->getChildren());
        $this->assertSame('API', $vertical->getChildren()[0]->getName());
    }

    public function test_from_array_throws_when_name_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vertical data must contain "name"');

        Vertical::fromArray(['description' => 'No name']);
    }

    public function test_add_child_accepts_unique_identifier(): void
    {
        $parent = new Vertical('Root', null);
        $parent->setIdentifier('root');
        $child = new Vertical('Child', null);
        $child->setIdentifier('child');

        $parent->addChild($child);

        $this->assertCount(1, $parent->getChildren());
        $this->assertSame('child', $parent->getChildren()[0]->getIdentifier());
    }

    public function test_add_child_throws_on_duplicate_identifier_in_tree(): void
    {
        $parent = new Vertical('Root', null);
        $parent->setIdentifier('root');
        $child = new Vertical('Child', null);
        $child->setIdentifier('child');
        $parent->addChild($child);

        $duplicate = new Vertical('Other', null);
        $duplicate->setIdentifier('child');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add child: duplicate vertical identifier(s) in tree');

        $parent->addChild($duplicate);
    }

    public function test_to_array_and_from_array_round_trip(): void
    {
        $original = new Vertical('News', 'News articles');
        $original->setIdentifier('news');
        $child = new Vertical('Tech', 'Tech news');
        $child->setIdentifier('tech');
        $original->addChild($child);

        $restored = Vertical::fromArray($original->toArray());

        $this->assertSame($original->getIdentifier(), $restored->getIdentifier());
        $this->assertSame($original->getName(), $restored->getName());
        $this->assertSame($original->getDescription(), $restored->getDescription());
        $this->assertCount(1, $restored->getChildren());
        $this->assertSame('tech', $restored->getChildren()[0]->getIdentifier());
    }
}
