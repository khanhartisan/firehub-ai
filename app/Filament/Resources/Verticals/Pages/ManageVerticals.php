<?php

namespace App\Filament\Resources\Verticals\Pages;

use App\Filament\Resources\Verticals\VerticalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ManageVerticals extends ManageRecords
{
    protected static string $resource = VerticalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()->whereNull('parent_id');
    }
}
