<?php

namespace App\ModelListeners\Page;

use App\Contracts\Model\PageCountable;
use App\Enums\ScrapableType;
use App\Enums\ScrapingStatus;
use App\Models\Page;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class PageCountListener extends ModelListener implements ModelListenerInterface
{
    /**
     * Listeners with higher priority will run first.
     *
     * @return int
     */
    public function priority(): int
    {
        return -10;
    }

    /**
     * Listen to the events of the given model.
     *
     * @return string
     */
    public function modelClass(): string
    {
        return Page::class;
    }

    /**
     * The list of all the events to listen to.
     *
     * @return array<string>
     */
    public function events(): array
    {
        return ['created', 'deleted', 'updated'];
    }

    /**
     * Handle the event.
     *
     * @param Page $page
     * @param string $event
     * @return void
     */
    protected function _handle(Page $page, string $event): void
    {
        if ($event === 'created') {
            $this->incrementCounts($page, 1);
            return;
        }

        if ($event === 'deleted') {
            $this->incrementCounts($page, -1);
            return;
        }

        if ($event === 'updated') {
            if (!$page->isDirty('type')
                and !$page->isDirty('scraping_status')
            ) {
                return;
            }

            $oldType = $page->getOriginal('type');
            $oldScrapingStatus = $page->getOriginal('scraping_status');

            $this->adjustCounts($page, $oldType, $oldScrapingStatus, -1);
            $this->incrementCounts($page, 1);
        }
    }

    private function incrementCounts(Page $page, int $delta): void
    {
        if ($page->type === null || $page->scraping_status === null) {
            return;
        }
        $this->adjustCounts($page, $page->type, $page->scraping_status, $delta);
    }

    private function adjustCounts(
        Page $page,
        ScrapableType $scrapableType,
        ScrapingStatus $scrapingStatus,
        int $delta
    ): void {
        foreach ($page->getPageCountableResources() as $resource) {
            if (!$resource instanceof PageCountable) {
                continue;
            }
            $resource->adjustPageCount($scrapableType, $scrapingStatus, $delta);
        }
    }
}
