<?php

namespace Tests\Unit\Services\Synthesizer\OutlineBuilder;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use Tests\TestCase;

class OutlineAndAuthorDriversTest extends TestCase
{
    public function test_outline_builder_creates_three_sections_and_includes_prompt(): void
    {
        $driver = new BasicOutlineBuilderDriver;
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.')
            ->setInstructions(['Focus on practical impact']);

        $outline = $driver->outline($brief, 'Add trade-offs section');

        $this->assertSame('AI weekly', $outline->getTitle());
        $this->assertCount(3, $outline->getItems());
        $this->assertSame('Introduction', $outline->getItems()[0]->getHeading());
        $this->assertSame('Main insights', $outline->getItems()[1]->getHeading());
        $this->assertContains('Add trade-offs section', $outline->getItems()[1]->getInstructions());
    }

    public function test_author_driver_builds_markdown_sections_from_outline(): void
    {
        $author = new BasicAuthorDriver;
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.');

        $outline = (new \App\Contracts\Synthesizer\OutlineBuilder\Outline)
            ->setItems([
                (new OutlineItem)->setHeading('Intro')->setBrief('Opening')->setInstructions(['Keep concise']),
                (new OutlineItem)->setHeading('Body')->setBrief('Details')->setInstructions(['Use bullets']),
            ]);

        $draft = $author->draft($brief, $outline, 'Be practical');
        $markdown = (string) $draft->getBodyMarkdown();

        $this->assertSame('AI weekly', $draft->getTitle());
        $this->assertStringContainsString('## Intro', $markdown);
        $this->assertStringContainsString('## Body', $markdown);
        $this->assertStringContainsString('## Additional prompt', $markdown);
        $this->assertStringContainsString('Be practical', $markdown);
    }
}
