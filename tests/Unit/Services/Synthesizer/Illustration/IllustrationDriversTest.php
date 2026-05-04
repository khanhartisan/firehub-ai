<?php

namespace Tests\Unit\Services\Synthesizer\Illustration;

use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Enums\AspectRatio;
use App\Services\Synthesizer\Illustration\Director\Drivers\BasicDirectorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\BasicIllustratorDriver;
use Tests\TestCase;

class IllustrationDriversTest extends TestCase
{
    public function test_basic_director_maps_context_into_direction(): void
    {
        $context = (new IllustrationContext())
            ->setSubject('A robot helping a developer')
            ->setGoal('Show calm productivity')
            ->setStyle('clean editorial illustration')
            ->setMacroContext('Modern startup office in daytime')
            ->setMicroContext('Character centered with balanced spacing');

        $director = new BasicDirectorDriver;
        $direction = $director->direct($context);

        $concept = $direction->getConceptContextValue();
        $style = $direction->getArtStyleContextValue();
        $camera = $direction->getCameraAndLightingContextValue();

        $this->assertIsArray($concept);
        $this->assertIsArray($style);
        $this->assertIsArray($camera);
        $this->assertSame('A robot helping a developer', $concept['primary_subject']['value'] ?? null);
        $this->assertSame('Show calm productivity', $concept['narrative_intent']['value'] ?? null);
        $this->assertSame('clean editorial illustration', $style['style']['value'] ?? null);
        $this->assertSame(
            ['Character centered with balanced spacing'],
            $camera['compositional_rules']['value'] ?? null
        );
    }

    public function test_basic_director_picks_first_provided_illustrator_then_fallback(): void
    {
        $context = new IllustrationContext();
        $direction = new IllustrationDirection();

        $director = new BasicDirectorDriver();
        $customIllustrator = new BasicIllustratorDriver();

        $picked = $director->determineIllustrator($context, $direction, [$customIllustrator]);
        $fallback = $director->determineIllustrator($context, $direction, []);

        $this->assertSame($customIllustrator, $picked);
        $this->assertInstanceOf(BasicIllustratorDriver::class, $fallback);
    }

    public function test_basic_illustrator_generates_deterministic_seed_and_applies_aspect_ratio(): void
    {
        $context = (new IllustrationContext())
            ->setSubject('A focused team at whiteboard')
            ->setGoal('Explain architecture planning')
            ->setAspectRatio(AspectRatio::LANDSCAPE_WIDE);

        $direction = (new BasicDirectorDriver())->direct($context);
        $illustrator = new BasicIllustratorDriver();

        $resultA = $illustrator->generate($context, $direction);
        $resultB = $illustrator->generate($context, $direction);

        $this->assertSame(AspectRatio::LANDSCAPE_WIDE, $resultA->getAspectRatio());
        $this->assertNotNull($resultA->getSeed());
        $this->assertSame($resultA->getSeed(), $resultB->getSeed());
        $this->assertSame($context, $resultA->getIllustrationContext());
    }

    public function test_illustration_result_serializes_illustration_context_round_trip(): void
    {
        $context = (new IllustrationContext())
            ->setSubject('Test subject')
            ->setGoal('Test goal');

        $original = (new IllustrationResult())
            ->setIllustrationContext($context)
            ->setAspectRatio(AspectRatio::SQUARE)
            ->setSeed('abc123');

        $expectedId = $original->getIdentifier();

        $restored = IllustrationResult::fromArray($original->toArray());

        $this->assertSame($expectedId, $restored->getIdentifier());
        $this->assertNotNull($restored->getIllustrationContext());
        $this->assertSame('Test subject', $restored->getIllustrationContext()->getSubjectValue());
        $this->assertSame('Test goal', $restored->getIllustrationContext()->getGoalValue());
        $this->assertSame(AspectRatio::SQUARE, $restored->getAspectRatio());
        $this->assertSame('abc123', $restored->getSeed());
    }

    public function test_illustration_result_from_array_omitted_identifier_assigns_on_first_access(): void
    {
        $context = (new IllustrationContext())->setSubject('Only context');

        $restored = IllustrationResult::fromArray([
            'illustration_context' => $context->toArray(),
        ]);

        $id = $restored->getIdentifier();
        $this->assertIsString($id);
        $this->assertNotSame('', $id);
        $this->assertSame('Only context', $restored->getIllustrationContext()?->getSubjectValue());
    }
}

