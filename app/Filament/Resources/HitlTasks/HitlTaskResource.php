<?php

namespace App\Filament\Resources\HitlTasks;

use App\Contracts\HitlGateway\TaskStatus;
use App\Filament\Resources\HitlTasks\Pages\ManageHitlTasks;
use App\Filament\Resources\HitlTasks\Pages\ViewHitlTask;
use App\Filament\Support\JsonField;
use App\Models\HitlTask;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HitlTaskResource extends Resource
{
    protected static ?string $model = HitlTask::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'HITL Tasks';

    protected static ?string $modelLabel = 'HITL Task';

    protected static ?string $pluralModelLabel = 'HITL Tasks';

    protected static string|\UnitEnum|null $navigationGroup = 'Human In The Loop';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Task')
                    ->schema([
                        Select::make('hitl_platform_id')
                            ->relationship('hitlPlatform', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('status')
                            ->options(collect(TaskStatus::cases())->mapWithKeys(
                                fn (TaskStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required()
                            ->default(TaskStatus::PENDING->value),
                        TextInput::make('title')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                        TextInput::make('internal_reference')
                            ->label('Internal reference')
                            ->maxLength(255)
                            ->disabledOn('edit'),
                        TextInput::make('hitl_platform_reference')
                            ->label('Platform reference')
                            ->maxLength(255),
                        JsonField::make('data', 'Task payload (JSON).'),
                        JsonField::make('conclusion', 'Task conclusion (JSON).'),
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
                TextColumn::make('hitlPlatform.name')
                    ->label('Platform')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TaskStatus
                        ? $state->name
                        : (string) $state)
                    ->sortable(),
                TextColumn::make('internal_reference')
                    ->label('Internal ref')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('hitl_platform_reference')
                    ->label('Platform ref')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(TaskStatus::cases())->mapWithKeys(
                        fn (TaskStatus $status): array => [$status->value => $status->name]
                    )->all()),
                SelectFilter::make('hitl_platform_id')
                    ->label('Platform')
                    ->relationship('hitlPlatform', 'name')
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
                Section::make('Task')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('title')->placeholder('—'),
                        TextEntry::make('hitlPlatform.name')->label('Platform'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof TaskStatus
                                ? $state->name
                                : (string) $state),
                        TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('internal_reference')
                            ->label('Internal reference')
                            ->placeholder('—'),
                        TextEntry::make('hitl_platform_reference')
                            ->label('Platform reference')
                            ->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHitlTasks::route('/'),
            'view' => ViewHitlTask::route('/{record}'),
        ];
    }
}
