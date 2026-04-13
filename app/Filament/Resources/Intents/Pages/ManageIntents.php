<?php

namespace App\Filament\Resources\Intents\Pages;

use App\Filament\Resources\Intents\IntentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIntents extends ManageRecords
{
    protected static string $resource = IntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
