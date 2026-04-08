<?php

namespace App\Filament\Resources\Verticals\Pages;

use App\Filament\Resources\Verticals\VerticalResource;
use App\Models\Vertical;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVertical extends ViewRecord
{
    protected static string $resource = VerticalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();
        $record = $this->getRecord();

        $breadcrumbs = $this->getResourceBreadcrumbs();

        foreach ($this->getAncestorVerticals() as $ancestor) {
            if (! $resource::hasRecordTitle()) {
                continue;
            }

            if ($resource::hasPage('view') && $resource::canView($ancestor)) {
                $breadcrumbs[$resource::getUrl('view', ['record' => $ancestor], shouldGuessMissingParameters: true)] = $resource::getRecordTitle($ancestor);
            } elseif ($resource::hasPage('edit') && $resource::canEdit($ancestor)) {
                $breadcrumbs[$resource::getUrl('edit', ['record' => $ancestor], shouldGuessMissingParameters: true)] = $resource::getRecordTitle($ancestor);
            } else {
                $breadcrumbs[] = $resource::getRecordTitle($ancestor);
            }
        }

        if ($record->exists && $resource::hasRecordTitle()) {
            if ($resource::hasPage('view') && $resource::canView($record)) {
                $breadcrumbs[$this->getResourceUrl('view')] = $this->getRecordTitle();
            } elseif ($resource::hasPage('edit') && $resource::canEdit($record)) {
                $breadcrumbs[$this->getResourceUrl('edit')] = $this->getRecordTitle();
            } else {
                $breadcrumbs[] = $this->getRecordTitle();
            }
        }

        $breadcrumbs[] = $this->getBreadcrumb();

        return $breadcrumbs;
    }

    /**
     * Ancestors from root down to the immediate parent (not including the viewed record).
     *
     * @return list<Vertical>
     */
    protected function getAncestorVerticals(): array
    {
        $chain = [];
        $parentId = $this->getRecord()->parent_id;

        while ($parentId !== null) {
            $parent = Vertical::query()->find($parentId);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $parentId = $parent->parent_id;
        }

        return array_reverse($chain);
    }
}
