<?php

namespace App\Filament\Resources\PublishingSchedules\Pages;

use App\Filament\Resources\PublishingSchedules\PublishingScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePublishingSchedules extends ManageRecords
{
    protected static string $resource = PublishingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
