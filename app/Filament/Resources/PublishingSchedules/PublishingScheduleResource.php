<?php

namespace App\Filament\Resources\PublishingSchedules;

use App\Contracts\Model\PublishingSchedule\Context as PublishingScheduleContext;
use App\Enums\PublishingScheduleStatus;
use App\Filament\Resources\PublishingSchedules\Pages\ManagePublishingSchedules;
use App\Filament\Resources\PublishingSchedules\Pages\ViewPublishingSchedule;
use App\Filament\Support\SemanticContextForm;
use App\Models\Article;
use App\Models\PublishingSchedule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PublishingScheduleResource extends Resource
{
    protected static ?string $model = PublishingSchedule::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?int $navigationSort = 450;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Publishing schedule')
                    ->schema([
                        Select::make('channel_id')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('publishable_type')
                            ->datalist([
                                Article::class,
                            ])
                            ->required(),
                        Select::make('status')
                            ->options(collect(PublishingScheduleStatus::cases())->mapWithKeys(
                                fn (PublishingScheduleStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required(),
                        TextInput::make('cron')
                            ->required()
                            ->helperText('Cron expression, e.g. 0 9 * * 1'),
                        DateTimePicker::make('next_execution_at')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    ->columns(2),
                ...SemanticContextForm::components(
                    PublishingScheduleContext::class,
                    heading: 'Schedule context',
                    description: 'Optional semantic context for scheduled publishing.',
                ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('publishable_type')
                    ->label('Publishable type')
                    ->formatStateUsing(fn (?string $state): string => class_basename((string) $state))
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('cron')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('next_execution_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('next_execution_at')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PublishingScheduleStatus::cases())->mapWithKeys(
                        fn (PublishingScheduleStatus $status): array => [$status->value => $status->name]
                    )->all()),
                SelectFilter::make('channel_id')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
                Section::make('Publishing schedule')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('channel.name')->label('Channel')->placeholder('—'),
                        TextEntry::make('publishable_type')->label('Publishable type'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('cron'),
                        TextEntry::make('next_execution_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePublishingSchedules::route('/'),
            'view' => ViewPublishingSchedule::route('/{record}'),
        ];
    }
}
