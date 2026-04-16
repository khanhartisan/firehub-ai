<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleIdeaStage
{
    protected function handleIdeaStage(): ?bool
    {
        if (! $this->article instanceof Article) {
            return false;
        }

        $context = trim((string) $this->article->context);
        if ($context === '') {
            return false;
        }

        $ideaForge = Synthesizer::getIdeaForge();
        $ideaAuditReports = [];

        foreach ($ideaForge->getIdeaAdvisors() as $ideaAdvisor) {
            $temporalSuggestions = $ideaAdvisor->suggestTemporal($this->client->id, $context);
            $intentTypeSuggestions = $ideaAdvisor->suggestIntentTypes($this->client->id, $context);
            $ideas = $ideaAdvisor->brainstorm($temporalSuggestions, $intentTypeSuggestions, $context, 5);

            foreach ($ideas as $idea) {
                $uniqueness = $ideaForge->getIdeaAuditor()->isIdeaUnique($this->client->id, $idea);
                if ($uniqueness->getIsUnique() === false) {
                    continue;
                }

                $ideaAuditReports[] = $ideaForge->getIdeaAuditor()->audit($idea);
            }
        }

        $pickedReports = $ideaForge->getIdeaPicker()->pick($ideaAuditReports, $context, 1);
        if (! $pickedReports) {
            return false;
        }

        $pickedReport = collect($pickedReports)
            ->first(fn ($report) => $report instanceof IdeaAuditReport);
        if (! $pickedReport instanceof IdeaAuditReport) {
            return false;
        }

        $stageData = is_array($this->article->stage_data) ? $this->article->stage_data : [];
        $stageData['idea'] = [
            'picked_report' => $pickedReport->toArray(),
        ];

        $this->article->stage_data = $stageData;
        $this->article->temporal = $pickedReport->getIdea()->getIntent()->getTemporal();
        $this->article->save();

        return true;
    }
}