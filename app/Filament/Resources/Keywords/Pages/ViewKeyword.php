<?php

namespace App\Filament\Resources\Keywords\Pages;

use App\Filament\Resources\Keywords\KeywordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKeyword extends ViewRecord
{
    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
