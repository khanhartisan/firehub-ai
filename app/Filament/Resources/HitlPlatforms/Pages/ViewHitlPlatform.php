<?php

namespace App\Filament\Resources\HitlPlatforms\Pages;

use App\Filament\Resources\HitlPlatforms\HitlPlatformResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHitlPlatform extends ViewRecord
{
    protected static string $resource = HitlPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
