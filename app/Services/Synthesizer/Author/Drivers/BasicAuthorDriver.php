<?php

namespace App\Services\Synthesizer\Author\Drivers;

use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Services\Synthesizer\Author\AuthorService;

class BasicAuthorDriver extends AuthorService
{
    public function draft(Brief $brief, Outline $outline, ?string $prompt = null): Draft
    {
        $sections = [];
        foreach ($outline->getItems() as $item) {
            $body = trim((string) $item->getBrief());
            $instructions = $item->getInstructions();
            if ($instructions !== []) {
                $body .= "\n\n".'- '.implode("\n- ", $instructions);
            }

            $sections[] = sprintf("## %s\n\n%s", $item->getHeading(), trim($body));
        }

        if ($prompt) {
            $sections[] = "## Additional prompt\n\n{$prompt}";
        }

        $bodyMarkdown = implode("\n\n", $sections);

        return (new Draft)
            ->setTitle($brief->getTitle())
            ->setExcerpt($brief->getDescription())
            ->setBodyMarkdown($bodyMarkdown);
    }
}
