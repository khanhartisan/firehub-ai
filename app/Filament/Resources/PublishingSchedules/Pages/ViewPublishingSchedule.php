<?php

namespace App\Filament\Resources\PublishingSchedules\Pages;

use App\Filament\Resources\PublishingSchedules\PublishingScheduleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPublishingSchedule extends ViewRecord
{
    protected static string $resource = PublishingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
