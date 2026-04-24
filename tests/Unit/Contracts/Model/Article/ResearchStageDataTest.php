<?php

namespace Tests\Unit\Contracts\Model\Article;

use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use Tests\TestCase;

class ResearchStageDataTest extends TestCase
{
    public function test_stage_data_round_trip_keeps_research_points_grouped_by_page(): void
    {
        $points = [
            (new RelevantPoint)
                ->setHeadline('Adoption is increasing')
                ->setDescription('Survey trends show sustained growth.')
                ->setEvidences(['68% weekly usage in surveyed teams'])
                ->setRationale('Adoption trend is clearly positive.')
                ->setRelevance(0.91),
        ];

        $conflict = (new ConflictedPoints)
            ->setRationale('ROI magnitude differs across sources.')
            ->setPoints([
                (new RelevantPoint)
                    ->setHeadline('ROI is ~2x')
                    ->setDescription('Vendor benchmark reports 2x gain.')
                    ->setEvidences(['Vendor report'])
                    ->setRationale('Aggressive estimate')
                    ->setRelevance(0.72),
                (new RelevantPoint)
                    ->setHeadline('ROI is ~1.2x')
                    ->setDescription('Independent study reports lower gain.')
                    ->setEvidences(['Independent study'])
                    ->setRationale('Conservative estimate')
                    ->setRelevance(0.69),
            ]);

        $stageData = new StageData;
        $stageData->getResearchStageData()
            ->setKeywords([
                (new KeywordData('ai copilots'))->setLanguage(Language::EN),
                (new KeywordData('developer productivity'))->setLanguage(Language::EN),
            ])
            ->setPagePoints('https://example.com/page-1/', $points)
            ->setPoints($points)
            ->setConflicts([$conflict]);

        $restored = StageData::fromArray($stageData->toArray());
        $research = $restored->getResearchStageData();

        $this->assertCount(2, $research->getKeywords());
        $this->assertSame('ai copilots', $research->getKeywords()[0]->getKeyword());
        $this->assertArrayHasKey('https://example.com/page-1', $research->getPointsByPageUrl());
        $this->assertSame(
            'Adoption is increasing',
            $research->getPointsByPageUrl()['https://example.com/page-1'][0]->getHeadline()
        );
        $this->assertCount(1, $research->getPoints());
        $this->assertCount(1, $research->getConflicts());
        $this->assertSame('ROI magnitude differs across sources.', $research->getConflicts()[0]->getRationale());
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
