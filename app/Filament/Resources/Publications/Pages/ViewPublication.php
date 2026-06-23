<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Enums\PublicationStatus;
use App\Filament\Resources\Publications\PublicationResource;
use App\Jobs\DispatchPublishingJob;
use App\Models\Publication;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPublication extends ViewRecord
{
    protected static string $resource = PublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (Publication $record): bool => (bool) $record->status?->isRetriable())
                ->requiresConfirmation()
                ->action(function (Publication $record): void {
                    $record->status = PublicationStatus::PENDING;
                    $record->attempts = 0;
                    $record->save();

                    DispatchPublishingJob::dispatch();

                    Notification::make()
                        ->title('Publication queued for retry')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
