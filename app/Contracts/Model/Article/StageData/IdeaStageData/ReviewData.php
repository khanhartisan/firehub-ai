<?php

namespace App\Contracts\Model\Article\StageData\IdeaStageData;

use App\Concerns\Serializable;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;

final class ReviewData implements \App\Contracts\Serializable
{
    use Serializable;

    public function __construct(
        protected ?IdeaUniquenessReport $uniquenessReport = null,
        protected ?IdeaAuditReport $auditReport = null,
    ) {
    }

    public function getUniquenessReport(): ?IdeaUniquenessReport
    {
        return $this->uniquenessReport;
    }

    public function setUniquenessReport(?IdeaUniquenessReport $uniquenessReport): static
    {
        $this->uniquenessReport = $uniquenessReport;

        return $this;
    }

    public function getAuditReport(): ?IdeaAuditReport
    {
        return $this->auditReport;
    }

    public function setAuditReport(?IdeaAuditReport $auditReport): static
    {
        $this->auditReport = $auditReport;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'uniqueness' => $this->uniquenessReport?->toArray(),
            'audit_report' => $this->auditReport?->toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $dto = new static;

        if (isset($data['uniqueness']) && is_array($data['uniqueness'])) {
            $dto->setUniquenessReport(IdeaUniquenessReport::fromArray($data['uniqueness']));
        }

        if (isset($data['audit_report']) && is_array($data['audit_report'])) {
            $dto->setAuditReport(IdeaAuditReport::fromArray($data['audit_report']));
        }

        return $dto;
    }
}
