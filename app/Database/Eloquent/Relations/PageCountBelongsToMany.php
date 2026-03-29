<?php

namespace App\Database\Eloquent\Relations;

use App\Contracts\Model\PageCountable;
use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PageCountBelongsToMany extends BelongsToMany
{
    private bool $syncPageCounts = false;

    public function syncPageCounts(bool $enabled = true): static
    {
        $this->syncPageCounts = $enabled;
        return $this;
    }

    public function attach($ids, array $attributes = [], $touch = true): void
    {
        $ids = $this->parseIds($ids);

        parent::attach($ids, $attributes, $touch);

        $this->adjustPageCountsForRelatedIds($ids, 1);
    }

    public function detach($ids = null, $touch = true): int
    {
        $idsToDetach = $ids === null
            ? $this->allRelatedIds()->all()
            : $this->parseIds($ids);

        $result = parent::detach($ids, $touch);

        $this->adjustPageCountsForRelatedIds($idsToDetach, -1);

        return $result;
    }

    private function adjustPageCountsForRelatedIds(array $relatedIds, int $delta): void
    {
        if (!$this->syncPageCounts || $delta === 0 || $relatedIds === []) {
            return;
        }

        $page = $this->getParent();

        if (!$page instanceof Page || $page->type === null || $page->scraping_status === null) {
            return;
        }

        /** @var Collection<int, mixed> $relatedModels */
        $relatedModels = $this->getRelated()
            ->newQuery()
            ->whereKey($relatedIds)
            ->get();

        foreach ($relatedModels as $relatedModel) {
            if (!$relatedModel instanceof PageCountable) {
                continue;
            }

            $relatedModel->adjustPageCount($page->type, $page->scraping_status, $delta);
        }
    }
}
