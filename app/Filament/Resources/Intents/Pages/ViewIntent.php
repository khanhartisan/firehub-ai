<?php

namespace App\Filament\Resources\Intents\Pages;

use App\Filament\Resources\Intents\IntentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIntent extends ViewRecord
{
    protected static string $resource = IntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
