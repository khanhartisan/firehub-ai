<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Models\Page;

trait FileEnrichmentStage
{
    protected function handleFileEnrichmentStage(Page $page): bool
    {
        if (env('APP_DEBUG')) {
            dump('Handling file enrichment stage, page '.$page->id);
        }

        if (!$snapshot = $page->currentSnapshot) {
            return false;
        }

        $pageData = $this->getPageDataForSnapshot($snapshot);
        $markdown = $pageData->getMarkdownContent();

        // TODO: Handle file enrichment
        return true;
    }
}