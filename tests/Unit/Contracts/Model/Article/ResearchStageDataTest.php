<?php

namespace Tests\Unit\Contracts\Model\Article;

use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\IdeaPoint;
use App\Contracts\Synthesizer\Researcher\IdeaPoints;
use App\Contracts\Synthesizer\Researcher\Point;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use Tests\TestCase;

class ResearchStageDataTest extends TestCase
{
    public function test_stage_data_round_trip_keeps_research_points_grouped_by_page(): void
    {
        $idea = new Idea($this->makeIntent(), 0.8, 'Strong fit');
        $ideaPoints = new IdeaPoints($idea, [
            new IdeaPoint(
                $idea,
                (new Point)
                    ->setHeadline('Adoption is increasing')
                    ->setDescription('Survey trends show sustained growth.')
                    ->setEvidences(['68% weekly usage in surveyed teams']),
                0.92
            ),
        ]);

        $stageData = new StageData;
        $stageData->getResearchStageData()
            ->setKeywords([
                (new KeywordData('ai copilots'))->setLanguage(Language::EN),
                (new KeywordData('developer productivity'))->setLanguage(Language::EN),
            ])
            ->setPageIdeaPoints('https://example.com/page-1/', $ideaPoints);

        $restored = StageData::fromArray($stageData->toArray());
        $research = $restored->getResearchStageData();

        $this->assertCount(2, $research->getKeywords());
        $this->assertSame('ai copilots', $research->getKeywords()[0]->getKeyword());
        $this->assertArrayHasKey('https://example.com/page-1', $research->getPointsByPageUrl());
        $this->assertSame(
            'Adoption is increasing',
            $research->getPointsByPageUrl()['https://example.com/page-1']->getIdeaPoints()[0]->getPoint()->getHeadline()
        );
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('AI adoption benchmarks')
            ->setDescription('How fast teams are adopting AI copilots.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
