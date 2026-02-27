<?php

namespace Tests\Unit\Services\VerticalResolver;

use App\Contracts\VerticalResolver\Vertical;
use App\Contracts\VerticalResolver\VerticalResolver;
use App\Services\VerticalResolver\Drivers\KeywordVerticalResolverDriver;
use App\Services\VerticalResolver\Drivers\OpenAIVerticalResolverDriver;
use Tests\TestCase;

class VerticalResolverServiceTest extends TestCase
{
    private function createResolver(array $config = []): VerticalResolver
    {
        return new KeywordVerticalResolverDriver($config);
    }

    private function vertical(string $name, ?string $description = null, ?string $identifier = null): Vertical
    {
        $v = new Vertical($name, $description);
        if ($identifier !== null) {
            $v->setIdentifier($identifier);
        }
        return $v;
    }

    public function test_resolve_returns_matches_for_matching_content(): void
    {
        $verticals = [
            $this->vertical('News', 'News articles and headlines'),
            $this->vertical('Docs', 'Documentation and technical docs'),
        ];

        $resolver = $this->createResolver([
            'match_threshold' => 0.3,
        ]);

        $content = 'This page contains news articles and latest headlines about technology.';
        $matches = $resolver->resolve($content, $verticals);

        $this->assertIsArray($matches);
        $identifiers = array_map(fn ($m) => $m->getVerticalIdentifier(), $matches);
        $this->assertContains('News', $identifiers, 'Content about news should match News vertical');
    }

    public function test_resolve_uses_match_threshold(): void
    {
        $verticals = [
            $this->vertical('News', 'news articles headlines'),
            $this->vertical('Other', 'miscellaneous'),
        ];

        $resolver = $this->createResolver([
            'match_threshold' => 0.8,
        ]);

        $content = 'Some news and articles here.';
        $matches = $resolver->resolve($content, $verticals);

        $this->assertIsArray($matches);
        foreach ($matches as $m) {
            $this->assertGreaterThanOrEqual(0, $m->getConfidence());
            $this->assertLessThanOrEqual(1.0, $m->getConfidence());
        }
    }

    public function test_resolve_with_empty_content_returns_empty_when_no_match(): void
    {
        $verticals = [
            $this->vertical('News', 'news'),
        ];

        $resolver = $this->createResolver();
        $matches = $resolver->resolve('', $verticals);

        $this->assertIsArray($matches);
        $this->assertEmpty($matches);
    }

    public function test_resolve_with_no_verticals_returns_empty_array(): void
    {
        $resolver = $this->createResolver(['match_threshold' => 0]);
        $matches = $resolver->resolve('news articles and documentation', []);

        $this->assertIsArray($matches);
        $this->assertEmpty($matches);
    }

    public function test_resolve_uses_vertical_identifier_when_set(): void
    {
        $verticals = [
            $this->vertical('News', 'News articles', 'news-id'),
        ];

        $resolver = $this->createResolver(['match_threshold' => 0.2]);
        $matches = $resolver->resolve('News articles and headlines', $verticals);

        $this->assertNotEmpty($matches);
        $this->assertSame('news-id', $matches[0]->getVerticalIdentifier());
    }

    public function test_propose_returns_empty_array_for_keyword_driver(): void
    {
        $verticals = [$this->vertical('News', 'news')];
        $resolver = $this->createResolver();

        $proposals = $resolver->propose('Some content', $verticals);

        $this->assertIsArray($proposals);
        $this->assertEmpty($proposals);
    }

    public function test_manager_returns_openai_driver_by_default(): void
    {
        $resolver = $this->app->make(VerticalResolver::class);

        $this->assertInstanceOf(OpenAIVerticalResolverDriver::class, $resolver);
    }
}
