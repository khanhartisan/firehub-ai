<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable;
use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;

final class IdeaStageData implements \App\Contracts\Serializable
{
    use Serializable;

    /** @var array<string, AdvisorData> */
    protected array $advisorDataByIdentifier = [];

    protected ?TemporalSuggestion $selectedTemporalSuggestion = null;
    protected ?IntentTypeSuggestion $selectedIntentTypeSuggestion = null;

    /** @var IdeaAuditReport[] */
    protected array $pickedReports = [];

    protected ?IdeaAuditReport $pickedReport = null;

    /** @var Idea[] */
    protected array $mergedIdeas = [];
    protected int $mergeCandidateIndex = 0;
    protected int $mergeCompareIndex = 0;

    /** @var Idea[] */
    protected array $uniqueIdeas = [];
    protected int $uniquenessIndex = 0;

    /** @var IdeaAuditReport[] */
    protected array $auditReports = [];
    protected int $auditIndex = 0;

    /** @return array<string, AdvisorData> */
    public function getAdvisorDataMap(): array
    {
        return $this->advisorDataByIdentifier;
    }

    public function getAdvisorDataByIdentifier(string $identifier, bool $autoCreate = false): ?AdvisorData
    {
        if ($autoCreate) {
            return $this->advisorDataByIdentifier[$identifier] ??= new AdvisorData();
        }

        return $this->advisorDataByIdentifier[$identifier] ?? null;
    }

    public function setAdvisorDataByIdentifier(string $identifier, AdvisorData $advisorData): static
    {
        $this->advisorDataByIdentifier[$identifier] = $advisorData;

        return $this;
    }

    public function hasSelectedTemporalSuggestion(): bool
    {
        return $this->selectedTemporalSuggestion instanceof TemporalSuggestion;
    }

    public function getSelectedTemporalSuggestion(): ?TemporalSuggestion
    {
        return $this->selectedTemporalSuggestion;
    }

    public function setSelectedTemporalSuggestion(?TemporalSuggestion $suggestion): static
    {
        $this->selectedTemporalSuggestion = $suggestion;

        return $this;
    }

    public function hasSelectedIntentTypeSuggestion(): bool
    {
        return $this->selectedIntentTypeSuggestion instanceof IntentTypeSuggestion;
    }

    public function getSelectedIntentTypeSuggestion(): ?IntentTypeSuggestion
    {
        return $this->selectedIntentTypeSuggestion;
    }

    public function setSelectedIntentTypeSuggestion(?IntentTypeSuggestion $suggestion): static
    {
        $this->selectedIntentTypeSuggestion = $suggestion;

        return $this;
    }

    /** @return IdeaAuditReport[] */
    public function getPickedReports(): array
    {
        return $this->pickedReports;
    }

    public function hasPickedReports(): bool
    {
        return $this->pickedReports !== [];
    }

    public function setPickedReports(array $pickedReports): static
    {
        $this->pickedReports = array_values(array_filter(
            $pickedReports,
            static fn ($v): bool => $v instanceof IdeaAuditReport
        ));

        return $this;
    }

    public function getPickedReport(): ?IdeaAuditReport
    {
        return $this->pickedReport;
    }

    public function setPickedReport(?IdeaAuditReport $pickedReport): static
    {
        $this->pickedReport = $pickedReport;

        return $this;
    }

    public function getPickedReportIdea(): ?Idea
    {
        if ($this->pickedReport instanceof IdeaAuditReport) {
            return $this->pickedReport->getIdea();
        }

        return $this->pickedReports[0]->getIdea() ?? null;
    }

    /** @return Idea[] */
    public function getMergedIdeas(): array
    {
        return $this->mergedIdeas;
    }

    public function setMergedIdeas(array $ideas): static
    {
        $this->mergedIdeas = array_values(array_filter($ideas, static fn ($v): bool => $v instanceof Idea));
        return $this;
    }

    public function getMergeCandidateIndex(): int
    {
        return max(0, $this->mergeCandidateIndex);
    }

    public function setMergeCandidateIndex(int $index): static
    {
        $this->mergeCandidateIndex = max(0, $index);
        return $this;
    }

    public function getMergeCompareIndex(): int
    {
        return max(0, $this->mergeCompareIndex);
    }

    public function setMergeCompareIndex(int $index): static
    {
        $this->mergeCompareIndex = max(0, $index);
        return $this;
    }

    /** @return Idea[] */
    public function getUniqueIdeas(): array
    {
        return $this->uniqueIdeas;
    }

    public function setUniqueIdeas(array $ideas): static
    {
        $this->uniqueIdeas = array_values(array_filter($ideas, static fn ($v): bool => $v instanceof Idea));
        return $this;
    }

    public function getUniquenessIndex(): int
    {
        return max(0, $this->uniquenessIndex);
    }

    public function setUniquenessIndex(int $index): static
    {
        $this->uniquenessIndex = max(0, $index);
        return $this;
    }

    /** @return IdeaAuditReport[] */
    public function getAuditReports(): array
    {
        return $this->auditReports;
    }

    public function setAuditReports(array $reports): static
    {
        $this->auditReports = array_values(array_filter($reports, static fn ($v): bool => $v instanceof IdeaAuditReport));
        return $this;
    }

    public function getAuditIndex(): int
    {
        return max(0, $this->auditIndex);
    }

    public function setAuditIndex(int $index): static
    {
        $this->auditIndex = max(0, $index);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'advisors' => array_map(static fn (AdvisorData $v) => $v->toArray(), $this->advisorDataByIdentifier),
            'selected_temporal_suggestion' => $this->selectedTemporalSuggestion?->toArray(),
            'selected_intent_type_suggestion' => $this->selectedIntentTypeSuggestion?->toArray(),
            'picked_reports' => array_map(static fn (IdeaAuditReport $v) => $v->toArray(), $this->pickedReports),
            'picked_report' => $this->pickedReport?->toArray(),
            'merged_ideas' => array_map(static fn (Idea $v) => $v->toArray(), $this->mergedIdeas),
            'merge_candidate_index' => $this->getMergeCandidateIndex(),
            'merge_compare_index' => $this->getMergeCompareIndex(),
            'unique_ideas' => array_map(static fn (Idea $v) => $v->toArray(), $this->uniqueIdeas),
            'uniqueness_index' => $this->getUniquenessIndex(),
            'audit_reports' => array_map(static fn (IdeaAuditReport $v) => $v->toArray(), $this->auditReports),
            'audit_index' => $this->getAuditIndex(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $dto = new static;

        if (isset($data['advisors']) && is_array($data['advisors'])) {
            foreach ($data['advisors'] as $identifier => $advisorData) {
                if (is_string($identifier) && is_array($advisorData)) {
                    $dto->setAdvisorDataByIdentifier($identifier, AdvisorData::fromArray($advisorData));
                }
            }

            // Backward compatibility for older list-based stage data.
            foreach (array_values(array_filter($data['advisors'], 'is_array')) as $index => $advisorData) {
                $legacyIdentifier = sprintf('legacy#%d', $index);
                if (! isset($dto->advisorDataByIdentifier[$legacyIdentifier])) {
                    $dto->setAdvisorDataByIdentifier($legacyIdentifier, AdvisorData::fromArray($advisorData));
                }
            }
        }

        if (isset($data['selected_temporal_suggestion']) && is_array($data['selected_temporal_suggestion'])) {
            $dto->setSelectedTemporalSuggestion(TemporalSuggestion::fromArray($data['selected_temporal_suggestion']));
        }

        if (isset($data['selected_intent_type_suggestion']) && is_array($data['selected_intent_type_suggestion'])) {
            $dto->setSelectedIntentTypeSuggestion(IntentTypeSuggestion::fromArray($data['selected_intent_type_suggestion']));
        }

        if (isset($data['picked_reports']) && is_array($data['picked_reports'])) {
            $dto->setPickedReports(array_map(
                static fn (array $v): IdeaAuditReport => IdeaAuditReport::fromArray($v),
                array_values(array_filter($data['picked_reports'], 'is_array'))
            ));
        }

        if (isset($data['picked_report']) && is_array($data['picked_report'])) {
            $dto->setPickedReport(IdeaAuditReport::fromArray($data['picked_report']));
        }

        if (isset($data['merged_ideas']) && is_array($data['merged_ideas'])) {
            $dto->setMergedIdeas(array_map(
                static fn (array $v): Idea => Idea::fromArray($v),
                array_values(array_filter($data['merged_ideas'], 'is_array'))
            ));
        }
        $dto->setMergeCandidateIndex((int) ($data['merge_candidate_index'] ?? 0));
        $dto->setMergeCompareIndex((int) ($data['merge_compare_index'] ?? 0));

        if (isset($data['unique_ideas']) && is_array($data['unique_ideas'])) {
            $dto->setUniqueIdeas(array_map(
                static fn (array $v): Idea => Idea::fromArray($v),
                array_values(array_filter($data['unique_ideas'], 'is_array'))
            ));
        }
        $dto->setUniquenessIndex((int) ($data['uniqueness_index'] ?? 0));

        if (isset($data['audit_reports']) && is_array($data['audit_reports'])) {
            $dto->setAuditReports(array_map(
                static fn (array $v): IdeaAuditReport => IdeaAuditReport::fromArray($v),
                array_values(array_filter($data['audit_reports'], 'is_array'))
            ));
        }
        $dto->setAuditIndex((int) ($data['audit_index'] ?? 0));

        return $dto;
    }
}
