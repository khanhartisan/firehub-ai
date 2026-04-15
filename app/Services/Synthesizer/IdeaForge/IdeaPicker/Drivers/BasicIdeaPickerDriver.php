<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers;

use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\IdeaPickerService;

class BasicIdeaPickerDriver extends IdeaPickerService
{
    public function pick(array $ideaAuditReports, string $context, int $limit = 1): ?array
    {
        $reports = array_values(array_filter(
            $ideaAuditReports,
            static fn ($report) => $report instanceof IdeaAuditReport
        ));

        if ($reports === []) {
            return null;
        }

        usort(
            $reports,
            static fn (IdeaAuditReport $left, IdeaAuditReport $right) => ($right->getScore() ?? 0) <=> ($left->getScore() ?? 0)
        );

        $limit = max(1, $limit);
        $picked = array_slice($reports, 0, $limit);

        return $picked === [] ? null : $picked;
    }
}
