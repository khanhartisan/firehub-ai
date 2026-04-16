<?php

namespace Tests\Unit\Services\Synthesizer\IdeaForge;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditor;
use App\Contracts\Synthesizer\IdeaForge\IdeaPicker;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use Tests\TestCase;

class IdeaForgeServiceTest extends TestCase
{
    public function test_it_stores_advisors_and_supports_add(): void
    {
        $advisorA = $this->makeAdvisor();
        $advisorB = $this->makeAdvisor();

        $ideaForge = new BasicIdeaForgeDriver(ideaAdvisors: [$advisorA]);
        $this->assertCount(1, $ideaForge->getIdeaAdvisors());
        $this->assertSame($advisorA, $ideaForge->getIdeaAdvisors()[0]);

        $this->assertSame($ideaForge, $ideaForge->addIdeaAdvisor($advisorB));
        $this->assertCount(2, $ideaForge->getIdeaAdvisors());
        $this->assertSame($advisorB, $ideaForge->getIdeaAdvisors()[1]);
    }

    public function test_it_sets_and_gets_auditor_and_picker(): void
    {
        $auditor = $this->makeAuditor();
        $picker = $this->makePicker();

        $ideaForge = new BasicIdeaForgeDriver;

        $this->assertSame($ideaForge, $ideaForge->setIdeaAuditor($auditor));
        $this->assertSame($ideaForge, $ideaForge->setIdeaPicker($picker));

        $this->assertSame($auditor, $ideaForge->getIdeaAuditor());
        $this->assertSame($picker, $ideaForge->getIdeaPicker());
    }

    public function test_it_throws_when_auditor_or_picker_is_missing(): void
    {
        $ideaForge = new BasicIdeaForgeDriver;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Idea auditor has not been configured.');
        $ideaForge->getIdeaAuditor();
    }

    public function test_it_throws_when_picker_is_missing(): void
    {
        $ideaForge = new BasicIdeaForgeDriver;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Idea picker has not been configured.');
        $ideaForge->getIdeaPicker();
    }

    protected function makeAdvisor(): IdeaAdvisor
    {
        return new class implements IdeaAdvisor
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

            public function suggestTemporal(string $clientId, string $context): array
            {
                return [];
            }

            public function suggestIntentTypes(string $clientId, string $context): array
            {
                return [];
            }

            public function brainstorm(array $temporalSuggestions, array $intentTypeSuggestions, string $context, int $limit = 5): array
            {
                return [];
            }
        };
    }

    protected function makeAuditor(): IdeaAuditor
    {
        return new class implements IdeaAuditor
        {
            public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport
            {
                return new IdeaUniquenessReport;
            }

            public function audit(Idea $idea): IdeaAuditReport
            {
                return new IdeaAuditReport($idea);
            }
        };
    }

    protected function makePicker(): IdeaPicker
    {
        return new class implements IdeaPicker
        {
            public function pick(array $ideaAuditReports, string $context, int $limit = 1): ?array
            {
                return [];
            }
        };
    }
}
