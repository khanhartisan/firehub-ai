<?php

namespace App\Filament\Resources\HitlTasks\Pages;

use App\Filament\Resources\HitlTasks\HitlTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHitlTasks extends ManageRecords
{
    protected static string $resource = HitlTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
