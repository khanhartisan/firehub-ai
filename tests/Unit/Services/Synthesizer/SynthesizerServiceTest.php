<?php

namespace Tests\Unit\Services\Synthesizer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Author\Author;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\BriefBuilder\BriefBuilder;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditor;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\IdeaForge\IdeaPicker;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder;
use App\Contracts\Synthesizer\Researcher\IdeaPoints;
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
        $authorA = $this->makeAuthor();

        $service = new SynthesizerService(
            ideaForge: $ideaForgeA,
            researcher: $researcherA,
            briefBuilder: $briefBuilderA,
            outlineBuilder: $outlineBuilderA,
            author: $authorA,
        );

        $this->assertSame($ideaForgeA, $service->getIdeaForge());
        $this->assertSame($researcherA, $service->getResearcher());
        $this->assertSame($briefBuilderA, $service->getBriefBuilder());
        $this->assertSame($outlineBuilderA, $service->getOutlineBuilder());
        $this->assertSame($authorA, $service->getAuthor());

        $ideaForgeB = $this->makeIdeaForge();
        $researcherB = $this->makeResearcher();
        $briefBuilderB = $this->makeBriefBuilder();
        $outlineBuilderB = $this->makeOutlineBuilder();
        $authorB = $this->makeAuthor();

        $this->assertSame($service, $service->setIdeaForge($ideaForgeB));
        $this->assertSame($service, $service->setResearcher($researcherB));
        $this->assertSame($service, $service->setBriefBuilder($briefBuilderB));
        $this->assertSame($service, $service->setOutlineBuilder($outlineBuilderB));
        $this->assertSame($service, $service->setAuthor($authorB));

        $this->assertSame($ideaForgeB, $service->getIdeaForge());
        $this->assertSame($researcherB, $service->getResearcher());
        $this->assertSame($briefBuilderB, $service->getBriefBuilder());
        $this->assertSame($outlineBuilderB, $service->getOutlineBuilder());
        $this->assertSame($authorB, $service->getAuthor());
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
            public function extractIdeaPoints(Idea $idea, string $content): IdeaPoints
            {
                return new IdeaPoints($idea);
            }
        };
    }

    protected function makeOutlineBuilder(): OutlineBuilder
    {
        return new class implements OutlineBuilder
        {
            public function outline(Brief $brief, ?string $prompt): Outline
            {
                return new Outline;
            }
        };
    }

    protected function makeAuthor(): Author
    {
        return new class implements Author
        {
            public function draft(Brief $brief, Outline $outline, ?string $prompt = null): Draft
            {
                return new Draft;
            }
        };
    }
}
