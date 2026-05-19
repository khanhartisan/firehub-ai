<?php

namespace Tests\Unit\Services\Synthesizer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\Synthesizer\Writer\Writer;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\BriefBuilder\BriefBuilder;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditor;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\IdeaForge\IdeaPicker;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Contracts\Synthesizer\Illustration\Director;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\Illustratable;
use App\Contracts\Synthesizer\Illustration\Illustrator;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\Editor\Editor;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\ConsolidationResult;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Contracts\Synthesizer\Researcher\Researcher;
use App\Services\Synthesizer\SynthesizerService;
use Tests\TestCase;

class SynthesizerServiceTest extends TestCase
{
    public function test_it_gets_and_sets_all_sub_services(): void
    {
        $ideaForgeA = $this->makeIdeaForge();
        $researcherA = $this->makeResearcher();
        $briefBuilderA = $this->makeBriefBuilder();
        $outlineBuilderA = $this->makeOutlineBuilder();
        $editorA = $this->makeEditor();
        $writerA = $this->makeWriter();
        $illustrationDirectorA = $this->makeIllustrationDirector();
        $illustratorA = $this->makeIllustrator();
        $illustratorB = $this->makeIllustrator();

        $service = new SynthesizerService(
            ideaForge: $ideaForgeA,
            researcher: $researcherA,
            briefBuilder: $briefBuilderA,
            outlineBuilder: $outlineBuilderA,
            editor: $editorA,
            writer: $writerA,
            illustrationDirector: $illustrationDirectorA,
            illustrators: [$illustratorA],
        );

        $this->assertSame($ideaForgeA, $service->getIdeaForge());
        $this->assertSame($researcherA, $service->getResearcher());
        $this->assertSame($briefBuilderA, $service->getBriefBuilder());
        $this->assertSame($outlineBuilderA, $service->getOutlineBuilder());
        $this->assertSame($editorA, $service->getEditor());
        $this->assertSame($writerA, $service->getWriter());
        $this->assertSame($illustrationDirectorA, $service->getIllustrationDirector());
        $this->assertSame([$illustratorA], $service->getIllustrators());

        $ideaForgeB = $this->makeIdeaForge();
        $researcherB = $this->makeResearcher();
        $briefBuilderB = $this->makeBriefBuilder();
        $outlineBuilderB = $this->makeOutlineBuilder();
        $editorB = $this->makeEditor();
        $writerB = $this->makeWriter();
        $illustrationDirectorB = $this->makeIllustrationDirector();

        $this->assertSame($service, $service->setIdeaForge($ideaForgeB));
        $this->assertSame($service, $service->setResearcher($researcherB));
        $this->assertSame($service, $service->setBriefBuilder($briefBuilderB));
        $this->assertSame($service, $service->setOutlineBuilder($outlineBuilderB));
        $this->assertSame($service, $service->setEditor($editorB));
        $this->assertSame($service, $service->setWriter($writerB));
        $this->assertSame($service, $service->setIllustrationDirector($illustrationDirectorB));
        $this->assertSame($service, $service->setIllustrators([$illustratorB, new \stdClass()]));

        $this->assertSame($ideaForgeB, $service->getIdeaForge());
        $this->assertSame($researcherB, $service->getResearcher());
        $this->assertSame($briefBuilderB, $service->getBriefBuilder());
        $this->assertSame($outlineBuilderB, $service->getOutlineBuilder());
        $this->assertSame($editorB, $service->getEditor());
        $this->assertSame($writerB, $service->getWriter());
        $this->assertSame($illustrationDirectorB, $service->getIllustrationDirector());
        $this->assertSame([$illustratorB], $service->getIllustrators());
    }

    protected function makeIdeaForge(): IdeaForge
    {
        return new class implements IdeaForge
        {
            public function getIdeaAdvisors(): array
            {
                return [];
            }

            public function setIdeaAdvisors(array $ideaAdvisors): static
            {
                return $this;
            }

            public function addIdeaAdvisor(IdeaAdvisor $ideaAdvisor): static
            {
                return $this;
            }

            public function getIdeaAuditor(): IdeaAuditor
            {
                return new class implements IdeaAuditor
                {
                    public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport
                    {
                        return (new IdeaUniquenessReport)
                            ->setClientId($clientId)
                            ->setIdeaIdentifier(trim((string) $idea->getIdentifier()));
                    }

                    public function audit(Idea $idea): \App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport
                    {
                        return new \App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport($idea);
                    }
                };
            }

            public function setIdeaAuditor(IdeaAuditor $auditor): static
            {
                return $this;
            }

            public function getIdeaPicker(): IdeaPicker
            {
                return new class implements IdeaPicker
                {
                    public function pick(array $ideaAuditReports, SemanticContext $context, int $limit = 1): ?array
                    {
                        return [];
                    }
                };
            }

            public function setIdeaPicker(IdeaPicker $picker): static
            {
                return $this;
            }
        };
    }

    protected function makeBriefBuilder(): BriefBuilder
    {
        return new class implements BriefBuilder
        {
            public function conceive(Idea $idea, SemanticContext $context): Brief
            {
                return new Brief;
            }
        };
    }

    protected function makeResearcher(): Researcher
    {
        return new class implements Researcher
        {
            public function extractIdeaPoints(Idea $idea, string $content): array
            {
                return [];
            }

            public function consolidateIdeaPoints(Idea $idea, array $points): ConsolidationResult
            {
                return new ConsolidationResult;
            }

            public function resolveIdeaConflictedPoints(Idea $idea, ConflictedPoints $conflictedPoints, array $facts): RelevantPoint
            {
                return new RelevantPoint;
            }
        };
    }

    protected function makeOutlineBuilder(): OutlineBuilder
    {
        return new class implements OutlineBuilder
        {
            public function outline(Brief $brief, ?SemanticContext $context): Outline
            {
                return new Outline;
            }
        };
    }

    protected function makeEditor(): Editor
    {
        return new class implements Editor
        {
            public function determineAuthorContext(Idea $idea, array $authorContexts): SemanticContext
            {
                return $authorContexts[0] ?? new SemanticContext;
            }

            public function distillOutlineAuthorContext(
                Outline $outline,
                string $outlineItemIdentifier,
                SemanticContext $authorContext,
                ?SemanticContext $generalContext = null
            ): SemanticContext {
                return $authorContext;
            }
        };
    }

    protected function makeWriter(): Writer
    {
        return new class implements Writer
        {
            public function draft(Brief $brief, Outline $outline, ?SemanticContext $context = null): Draft
            {
                return new Draft;
            }

            public function getIllustrationAnchors(Article $article, array $illustrationResults): array
            {
                return [];
            }
        };
    }

    protected function makeIllustrationDirector(): Director
    {
        return new class implements Director
        {
            public function resolveIllustrationContexts(
                Illustratable $illustratable,
                ?int $minContexts = null,
                ?int $maxContexts = null
            ): array {
                return [];
            }

            public function direct(IllustrationContext $context): IllustrationDirection
            {
                return new IllustrationDirection;
            }

            public function determineIllustrator(
                IllustrationContext $context,
                IllustrationDirection $direction,
                array $illustrators
            ): ?Illustrator {
                return $illustrators[0] ?? null;
            }
        };
    }

    protected function makeIllustrator(): Illustrator
    {
        return new class implements Illustrator
        {
            protected ?string $description = null;
            protected ?string $identifier = null;

            public function getDescription(): ?string
            {
                return $this->description;
            }

            public function setDescription(?string $description): static
            {
                $this->description = $description;
                return $this;
            }

            public function getIdentifier(): ?string
            {
                return $this->identifier;
            }

            public function setIdentifier(?string $identifier): static
            {
                $this->identifier = $identifier;
                return $this;
            }

            public function generate(IllustrationContext $context, IllustrationDirection $direction): IllustrationResult
            {
                return new IllustrationResult;
            }
        };
    }
}
