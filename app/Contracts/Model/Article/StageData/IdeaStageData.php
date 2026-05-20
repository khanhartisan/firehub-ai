<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;

final class IdeaStageData implements \App\Contracts\Serializable
{
    use Serializable;

    /** @var array<string, AdvisorData> */
    protected array $advisorDataByIdentifier = [];

    /** @var TemporalSuggestion[] */
    protected array $selectedTemporalSuggestions = [];
    /** @var IntentTypeSuggestion[] */
    protected array $selectedIntentTypeSuggestions = [];

    protected ?IdeaAuditReport $pickedIdeaAuditReport = null;

    protected ?AuthorContext $selectedAuthorContext = null;

    /** @var Idea[] */
    protected array $ideas = [];

    /** @var array<int, array{0: string, 1: string}> */
    protected array $uniqueIdeaIdentifierPairs = [];

    /**
     * One report per checked idea; {@see IdeaUniquenessReport::getIdeaIdentifier()} links to {@see Idea::getIdentifier()}.
     * An idea with no matching report is still pending uniqueness.
     *
     * @var list<IdeaUniquenessReport>
     */
    protected array $ideaUniquenessReports = [];

    /** @var IdeaAuditReport[] */
    protected array $ideaAuditReports = [];

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

        return $this->getAdvisorDataMap()[$identifier] ?? null;
    }

    public function setAdvisorDataByIdentifier(string $identifier, AdvisorData $advisorData): static
    {
        $this->advisorDataByIdentifier[$identifier] = $advisorData;

        return $this;
    }

    public function hasSelectedTemporalSuggestions(): bool
    {
        return count($this->getSelectedTemporalSuggestions()) > 0;
    }

    /** @return TemporalSuggestion[] */
    public function getSelectedTemporalSuggestions(): array
    {
        return $this->selectedTemporalSuggestions;
    }

    /** @param iterable<TemporalSuggestion> $suggestions */
    public function setSelectedTemporalSuggestions(iterable $suggestions): static
    {
        $this->selectedTemporalSuggestions = array_values(array_filter(
            is_array($suggestions) ? $suggestions : iterator_to_array($suggestions),
            static fn ($v): bool => $v instanceof TemporalSuggestion
        ));

        return $this;
    }

    public function hasSelectedIntentTypeSuggestions(): bool
    {
        return count($this->getSelectedIntentTypeSuggestions()) > 0;
    }

    /** @return IntentTypeSuggestion[] */
    public function getSelectedIntentTypeSuggestions(): array
    {
        return $this->selectedIntentTypeSuggestions;
    }

    /** @param iterable<IntentTypeSuggestion> $suggestions */
    public function setSelectedIntentTypeSuggestions(iterable $suggestions): static
    {
        $this->selectedIntentTypeSuggestions = array_values(array_filter(
            is_array($suggestions) ? $suggestions : iterator_to_array($suggestions),
            static fn ($v): bool => $v instanceof IntentTypeSuggestion
        ));

        return $this;
    }

    public function getPickedIdeaAuditReport(): ?IdeaAuditReport
    {
        return $this->pickedIdeaAuditReport;
    }

    public function setPickedIdeaAuditReport(?IdeaAuditReport $pickedIdeaAuditReport): static
    {
        $this->pickedIdeaAuditReport = $pickedIdeaAuditReport;

        return $this;
    }

    public function getPickedIdea(): ?Idea
    {
        return $this->getPickedIdeaAuditReport()?->getIdea();
    }

    public function hasSelectedAuthorContext(): bool
    {
        return $this->getSelectedAuthorContext() instanceof AuthorContext;
    }

    public function getSelectedAuthorContext(): ?AuthorContext
    {
        return $this->selectedAuthorContext;
    }

    public function setSelectedAuthorContext(?AuthorContext $selectedAuthorContext): static
    {
        $this->selectedAuthorContext = $selectedAuthorContext;

        return $this;
    }

    /** @return Idea[] */
    public function getIdeas(): array
    {
        return $this->ideas;
    }

    /**
     * Appends an idea when its trimmed identifier is non-empty and not already present (first wins).
     */
    public function addIdea(Idea $idea): static
    {
        $id = trim((string) $idea->getIdentifier());
        if ($id === '') {
            return $this;
        }

        if ($this->getIdeaByIdentifier($id) !== null) {
            return $this;
        }

        $this->ideas[] = $idea;

        return $this;
    }

    /**
     * Replaces the list, reusing {@see addIdea()} so the same validation and de-duplication apply.
     *
     * @param  iterable<Idea>  $ideas
     */
    public function setIdeas(iterable $ideas): static
    {
        $this->ideas = [];
        foreach ($ideas as $idea) {
            if (! $idea instanceof Idea) {
                continue;
            }

            $this->addIdea($idea);
        }

        return $this;
    }

    public function getIdeaByIdentifier(string $identifier): ?Idea
    {
        $key = trim($identifier);
        if ($key === '') {
            return null;
        }

        foreach ($this->getIdeas() as $idea) {
            if (trim((string) $idea->getIdentifier()) === $key) {
                return $idea;
            }
        }

        return null;
    }

    public function removeIdeaByIdentifier(string $identifier): static
    {
        $key = trim($identifier);
        if ($key === '') {
            return $this;
        }

        $this->ideas = array_values(array_filter(
            $this->getIdeas(),
            static fn (Idea $idea): bool => trim((string) $idea->getIdentifier()) !== $key
        ));

        return $this;
    }

    /** @return array<int, array{0: string, 1: string}> */
    public function getUniqueIdeaIdentifierPairs(): array
    {
        return $this->uniqueIdeaIdentifierPairs;
    }

    public function setUniqueIdeaIdentifierPairs(array $pairs): static
    {
        $normalizedPairs = [];
        $seen = [];
        foreach ($pairs as $pair) {
            if (! is_array($pair)) {
                continue;
            }

            $values = array_values($pair);
            if (count($values) < 2) {
                continue;
            }

            $left = trim((string) ($values[0] ?? ''));
            $right = trim((string) ($values[1] ?? ''));
            if ($left === '' || $right === '' || $left === $right) {
                continue;
            }

            $ordered = [$left, $right];
            sort($ordered);
            $pairKey = implode('|', $ordered);
            if (isset($seen[$pairKey])) {
                continue;
            }

            $seen[$pairKey] = true;
            $normalizedPairs[] = $ordered;
        }

        $this->uniqueIdeaIdentifierPairs = array_values($normalizedPairs);

        return $this;
    }

    /**
     * Reports whose {@see IdeaUniquenessReport::getIdeaIdentifier()} matches a current {@see Idea} only.
     * Stale rows may remain in internal storage until overwritten; they are never exposed here or in {@see toArray()}.
     *
     * @return list<IdeaUniquenessReport>
     */
    public function getIdeaUniquenessReports(): array
    {
        return array_values(array_filter(
            $this->ideaUniquenessReports,
            function (IdeaUniquenessReport $report): bool {
                if (!$id = trim((string) $report->getIdeaIdentifier())) {
                    return false;
                }
                return !!$this->getIdeaByIdentifier($id);
            }
        ));
    }

    /**
     * Inserts or replaces the report for {@see IdeaUniquenessReport::getIdeaIdentifier()} when that id exists
     * on some {@see Idea} in {@see $ideas}; otherwise no-op. Empty identifiers are ignored.
     */
    public function addIdeaUniquenessReport(IdeaUniquenessReport $report): static
    {
        if (!$id = trim((string) $report->getIdeaIdentifier())) {
            throw new \Exception('Idea identifier was not set, cannot add IdeaUniquenessReport');
        }

        if (!$this->getIdeaByIdentifier($id)) {
            throw new \Exception('Idea not found, cannot add IdeaUniquenessReport');
        }

        foreach ($this->ideaUniquenessReports as $index => $existing) {
            if (trim((string) $existing->getIdeaIdentifier()) === $id) {
                $this->ideaUniquenessReports[$index] = $report;

                return $this;
            }
        }

        $this->ideaUniquenessReports[] = $report;

        return $this;
    }

    /**
     * Replaces the whole list; later items with the same idea id win (via {@see addIdeaUniquenessReport()}).
     *
     * @param iterable<IdeaUniquenessReport> $reports
     * @throws \Exception
     */
    public function setIdeaUniquenessReports(iterable $reports): static
    {
        $this->ideaUniquenessReports = [];
        foreach ($reports as $report) {
            if (! $report instanceof IdeaUniquenessReport) {
                continue;
            }

            $this->addIdeaUniquenessReport($report);
        }

        return $this;
    }

    public function getIdeaUniquenessReport(string $ideaIdentifier): ?IdeaUniquenessReport
    {
        $key = trim($ideaIdentifier);
        if ($key === '') {
            return null;
        }

        return array_find($this->getIdeaUniquenessReports(), fn($report) => trim((string)$report->getIdeaIdentifier()) === $key);

    }

    /** @return IdeaAuditReport[] */
    public function getIdeaAuditReports(): array
    {
        return $this->ideaAuditReports;
    }

    /**
     * Appends or replaces the audit row for the report’s idea id. The idea must exist in {@see $ideas}.
     *
     * @throws \Exception
     */
    public function addIdeaAuditReport(IdeaAuditReport $report): static
    {
        $id = trim((string) $report->getIdea()->getIdentifier());
        if ($id === '') {
            throw new \Exception('Idea identifier empty on audit report');
        }

        if ($this->getIdeaByIdentifier($id) === null) {
            throw new \Exception('Idea not found, cannot add IdeaAuditReport');
        }

        foreach ($this->ideaAuditReports as $index => $existing) {
            if (trim((string) $existing->getIdea()->getIdentifier()) === $id) {
                $this->ideaAuditReports[$index] = $report;

                return $this;
            }
        }

        $this->ideaAuditReports[] = $report;

        return $this;
    }

    /**
     * Replaces the whole list; later items with the same idea id win (via {@see addIdeaAuditReport()}).
     *
     * @param iterable<IdeaAuditReport> $reports
     * @throws \Exception
     */
    public function setIdeaAuditReports(iterable $reports): static
    {
        $this->ideaAuditReports = [];
        foreach ($reports as $report) {
            if (! $report instanceof IdeaAuditReport) {
                continue;
            }

            $this->addIdeaAuditReport($report);
        }

        return $this;
    }

    public function getIdeaAuditReport(string $ideaIdentifier): ?IdeaAuditReport
    {
        $key = trim($ideaIdentifier);
        if ($key === '') {
            return null;
        }

        return array_find($this->getIdeaAuditReports(), fn($report) => trim((string)$report->getIdea()->getIdentifier()) === $key);

    }

    public function toArray(): array
    {
        return [
            'advisors' => array_map(static fn (AdvisorData $v) => $v->toArray(), $this->getAdvisorDataMap()),
            'selected_temporal_suggestions' => array_map(
                static fn (TemporalSuggestion $v) => $v->toArray(),
                $this->getSelectedTemporalSuggestions()
            ),
            'selected_intent_type_suggestions' => array_map(
                static fn (IntentTypeSuggestion $v) => $v->toArray(),
                $this->getSelectedIntentTypeSuggestions()
            ),
            'picked_idea_audit_report' => $this->getPickedIdeaAuditReport()?->toArray(),
            'selected_author_context' => $this->getSelectedAuthorContext()?->toArray(),
            'ideas' => array_map(static fn (Idea $v) => $v->toArray(), $this->getIdeas()),
            'unique_idea_identifier_pairs' => $this->getUniqueIdeaIdentifierPairs(),
            'idea_uniqueness_reports' => array_map(
                static fn (IdeaUniquenessReport $v) => $v->toArray(),
                $this->getIdeaUniquenessReports()
            ),
            'idea_audit_reports' => array_map(static fn (IdeaAuditReport $v) => $v->toArray(), $this->getIdeaAuditReports()),
        ];
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): static
    {
        $dto = new static;

        if (isset($data['advisors']) && is_array($data['advisors'])) {
            foreach ($data['advisors'] as $identifier => $advisorData) {
                if (! is_array($advisorData)) {
                    continue;
                }

                $dto->setAdvisorDataByIdentifier((string) $identifier, AdvisorData::fromArray($advisorData));
            }
        }

        if (isset($data['selected_temporal_suggestions']) && is_array($data['selected_temporal_suggestions'])) {
            $dto->setSelectedTemporalSuggestions(array_map(
                static fn (array $v): TemporalSuggestion => TemporalSuggestion::fromArray($v),
                array_values(array_filter($data['selected_temporal_suggestions'], 'is_array'))
            ));
        }

        if (isset($data['selected_intent_type_suggestions']) && is_array($data['selected_intent_type_suggestions'])) {
            $dto->setSelectedIntentTypeSuggestions(array_map(
                static fn (array $v): IntentTypeSuggestion => IntentTypeSuggestion::fromArray($v),
                array_values(array_filter($data['selected_intent_type_suggestions'], 'is_array'))
            ));
        }

        if (isset($data['picked_idea_audit_report']) && is_array($data['picked_idea_audit_report'])) {
            $dto->setPickedIdeaAuditReport(IdeaAuditReport::fromArray($data['picked_idea_audit_report']));
        }

        if (isset($data['selected_author_context']) && is_array($data['selected_author_context'])) {
            $dto->setSelectedAuthorContext(AuthorContext::fromArray($data['selected_author_context']));
        }

        if (isset($data['ideas']) && is_array($data['ideas'])) {
            $dto->setIdeas(array_map(
                static fn (array $v): Idea => Idea::fromArray($v),
                array_values(array_filter($data['ideas'], 'is_array'))
            ));
        }

        if (isset($data['unique_idea_identifier_pairs']) && is_array($data['unique_idea_identifier_pairs'])) {
            $dto->setUniqueIdeaIdentifierPairs($data['unique_idea_identifier_pairs']);
        }

        if (isset($data['idea_uniqueness_reports']) && is_array($data['idea_uniqueness_reports'])) {
            $loaded = [];
            foreach ($data['idea_uniqueness_reports'] as $report) {
                if (! is_array($report)) {
                    continue;
                }

                try {
                    $loaded[] = IdeaUniquenessReport::fromArray($report);
                } catch (\Throwable) {
                    continue;
                }
            }

            $dto->setIdeaUniquenessReports($loaded);
        }

        if (isset($data['idea_audit_reports']) && is_array($data['idea_audit_reports'])) {
            $dto->setIdeaAuditReports(array_map(
                static fn (array $v): IdeaAuditReport => IdeaAuditReport::fromArray($v),
                array_values(array_filter($data['idea_audit_reports'], 'is_array'))
            ));
        }

        return $dto;
    }
}
