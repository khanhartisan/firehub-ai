<?php

namespace App\Filament\Resources\Publications;

use App\Enums\PublicationStatus;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Filament\Resources\Publications\Pages\ViewPublication;
use App\Filament\Support\JsonField;
use App\Jobs\DispatchPublishingJob;
use App\Models\Article;
use App\Models\Publication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PublicationResource extends Resource
{
    protected static ?string $model = Publication::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?int $navigationSort = 500;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Publication')
                    ->schema([
                        Select::make('channel_id')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('publishable_type')
                            ->datalist([
                                Article::class,
                            ])
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('publishable_id')
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('title')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(collect(PublicationStatus::cases())->mapWithKeys(
                                fn (PublicationStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required(),
                        TextInput::make('reference')
                            ->maxLength(255),
                        TextInput::make('attempts')
                            ->numeric()
                            ->minValue(0),
                        DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->nullable(),
                        JsonField::make('meta', 'Publication metadata (JSON).'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('publishable_id')
                    ->label('Publishable')
                    ->toggleable(),
                TextColumn::make('reference')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('attempts')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PublicationStatus::cases())->mapWithKeys(
                        fn (PublicationStatus $status): array => [$status->value => $status->name]
                    )->all()),
                SelectFilter::make('channel_id')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Publication')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('title')->placeholder('—'),
                        TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('channel.name')->label('Channel'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('publishable_type')->label('Publishable type'),
                        TextEntry::make('publishable_id')->label('Publishable ID'),
                        TextEntry::make('reference')->placeholder('—'),
                        TextEntry::make('attempts'),
                        TextEntry::make('published_at')->dateTime()->placeholder('—'),
                        TextEntry::make('error_logs')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePublications::route('/'),
            'view' => ViewPublication::route('/{record}'),
        ];
    }
}
