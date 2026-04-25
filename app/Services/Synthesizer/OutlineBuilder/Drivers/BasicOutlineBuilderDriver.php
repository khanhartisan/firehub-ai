<?php

namespace App\Services\Synthesizer\OutlineBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Services\Synthesizer\OutlineBuilder\OutlineBuilderService;

class BasicOutlineBuilderDriver extends OutlineBuilderService
{
    public function outline(Brief $brief, ?SemanticContext $context): Outline
    {
        $title = $brief->getTitle() ?: 'Untitled draft';

        $introPoint = (new RelevantPoint)
            ->setHeadline('Introduction')
            ->setDescription('Set context and define the key problem.')
            ->setEvidences([]);
        $intro = (new OutlineItem)
            ->setPoint($introPoint)
            ->setGuidelines(['Use 1-2 short paragraphs.']);

        $bodyPoint = (new RelevantPoint)
            ->setHeadline('Main insights')
            ->setDescription($brief->getDescription())
            ->setEvidences([]);
        $body = (new OutlineItem)
            ->setPoint($bodyPoint)
            ->setGuidelines(array_merge(
                ['Prioritize practical takeaways.'],
                $brief->getInstructions(),
                $this->contextToInstructions($context)
            ));

        $conclusionPoint = (new RelevantPoint)
            ->setHeadline('Conclusion')
            ->setDescription('Summarize and recommend next steps.')
            ->setEvidences([]);
        $conclusion = (new OutlineItem)
            ->setPoint($conclusionPoint)
            ->setGuidelines(['Close with a concrete action.']);

        return (new Outline)
            ->setTitle($title)
            ->setItems([$intro, $body, $conclusion]);
    }

    /**
     * @return list<string>
     */
    protected function contextToInstructions(?SemanticContext $context): array
    {
        if (! $context instanceof SemanticContext) {
            return [];
        }

        $lines = [];
        foreach ($context->toArray() as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['value'])) {
                continue;
            }

            $value = trim((string) json_encode($entry['value'], JSON_UNESCAPED_UNICODE));
            if ($value === '' || $value === 'null') {
                continue;
            }

            $lines[] = sprintf('Use context "%s": %s', (string) $key, $value);
        }

        return $lines;
    }
}
