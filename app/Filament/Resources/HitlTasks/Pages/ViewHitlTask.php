<?php

namespace App\Filament\Resources\HitlTasks\Pages;

use App\Filament\Resources\HitlTasks\HitlTaskResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHitlTask extends ViewRecord
{
    protected static string $resource = HitlTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
