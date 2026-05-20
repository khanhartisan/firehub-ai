<?php

namespace Tests\Unit\Contracts\Model\Article;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use Tests\TestCase;

class IdeaStageDataTest extends TestCase
{
    public function test_selected_author_context_round_trip(): void
    {
        $idea = new Idea(
            (new Intent)
                ->setTitle('Distributed tracing for platform teams')
                ->setDescription('How SREs adopt tracing without slowing delivery.')
                ->setLanguage(Language::EN)
                ->setTemporal(Temporal::EVERGREEN)
                ->setTypes([IntentType::INFORMATIONAL])
        );

        $authorContext = (new AuthorContext)
            ->set('voice', 'Author voice', 'Direct, operator-first explanations');

        $ideaData = new IdeaStageData;
        $ideaData
            ->setPickedIdeaAuditReport(new IdeaAuditReport($idea, 0.9, ['Clear audience'], []))
            ->setSelectedAuthorContext($authorContext);

        $restored = IdeaStageData::fromArray($ideaData->toArray());

        $this->assertTrue($restored->hasSelectedAuthorContext());
        $this->assertSame(
            $authorContext->toArray(),
            $restored->getSelectedAuthorContext()->toArray()
        );
        $this->assertSame(
            $idea->getIdentifier(),
            $restored->getPickedIdea()?->getIdentifier()
        );
    }
}
