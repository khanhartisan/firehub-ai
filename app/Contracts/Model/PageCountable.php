<?php

namespace App\Contracts\Model;

use App\Enums\ScrapableType;
use App\Enums\ScrapingStatus;

/**
 * Contract for resources that maintain page counts by scrapable type and scraping status.
 *
 * Implemented by Vertical, Source, and Tag. When pages are created, updated,
 * or deleted, PageCountListener calls this to keep page_counts in sync for
 * dashboards and filtering.
 *
 * @see \App\ModelListeners\Page\PageCountListener
 */
interface PageCountable
{
    /**
     * Adjust the count for the given scrapable type and scraping status.
     *
     * @param  ScrapableType  $scrapableType  e.g. PAGE, UNCLASSIFIED
     * @param  ScrapingStatus  $scrapingStatus  e.g. SUCCESS, PENDING
     * @param  int  $delta  Change amount (e.g. +1 on create, -1 on delete)
     * @return bool  Whether the adjustment was applied
     */
    public function adjustPageCount(ScrapableType $scrapableType, ScrapingStatus $scrapingStatus, int $delta): bool;
}
