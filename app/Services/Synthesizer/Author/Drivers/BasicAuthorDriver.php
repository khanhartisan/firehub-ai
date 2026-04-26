<?php

namespace App\Services\Synthesizer\Author\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Services\Synthesizer\Author\AuthorService;

class BasicAuthorDriver extends AuthorService
{
    public function draft(Brief $brief, Outline $outline, ?SemanticContext $context = null): Draft
    {
        $sections = [];
        foreach ($outline->getItems() as $item) {
            $point = $item->getPoint();
            $body = trim((string) $point->getDescription());
            $instructions = $item->getGuidelines();
            if ($instructions === []) {
                $instructions = $point->getEvidences();
            }
            if ($instructions !== []) {
                $body .= "\n\n".'- '.implode("\n- ", $instructions);
            }

            $heading = trim((string) ($point->getHeadline() ?? ''));
            $sections[] = sprintf("## %s\n\n%s", $heading, trim($body));
        }

        $contextLines = $this->contextToLines($context);
        if ($contextLines !== []) {
            $sections[] = "## Additional context\n\n- ".implode("\n- ", $contextLines);
        }

        $bodyMarkdown = implode("\n\n", $sections);

        return (new Draft)
            ->setTitle($brief->getTitle())
            ->setExcerpt($brief->getDescription())
            ->setBodyMarkdown($bodyMarkdown);
    }

    /**
     * @return list<string>
     */
    protected function contextToLines(?SemanticContext $context): array
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
