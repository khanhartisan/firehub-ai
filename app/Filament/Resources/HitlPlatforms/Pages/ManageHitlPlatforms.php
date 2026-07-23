<?php

namespace App\Filament\Resources\HitlPlatforms\Pages;

use App\Filament\Resources\HitlPlatforms\HitlPlatformResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHitlPlatforms extends ManageRecords
{
    protected static string $resource = HitlPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
