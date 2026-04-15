<?php

namespace App\Services\Synthesizer\OutlineBuilder\Drivers;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Services\Synthesizer\OutlineBuilder\OutlineBuilderService;

class BasicOutlineBuilderDriver extends OutlineBuilderService
{
    public function outline(Brief $brief, ?string $prompt): Outline
    {
        $title = $brief->getTitle() ?: 'Untitled draft';

        $intro = (new OutlineItem)
            ->setHeading('Introduction')
            ->setBrief('Set context and define the key problem.')
            ->setInstructions(['Use 1-2 short paragraphs.']);

        $body = (new OutlineItem)
            ->setHeading('Main insights')
            ->setBrief($brief->getDescription())
            ->setInstructions(array_merge(
                ['Prioritize practical takeaways.'],
                $brief->getInstructions()
            ));

        $conclusion = (new OutlineItem)
            ->setHeading('Conclusion')
            ->setBrief('Summarize and recommend next steps.')
            ->setInstructions(['Close with a concrete action.']);

        if ($prompt) {
            $body->setInstructions(array_merge($body->getInstructions(), [$prompt]));
        }

        return (new Outline)
            ->setTitle($title)
            ->setItems([$intro, $body, $conclusion]);
    }
}
