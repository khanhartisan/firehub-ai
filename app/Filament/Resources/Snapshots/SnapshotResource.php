<?php

namespace App\Filament\Resources\Snapshots;

use App\Enums\ScrapingStatus;
use App\Filament\Resources\Snapshots\Pages\ManageSnapshots;
use App\Models\Snapshot;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SnapshotResource extends Resource
{
    protected static ?string $model = Snapshot::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Resources';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page & status')
                    ->schema([
                        Select::make('page_id')
                            ->relationship(
                                'page',
                                'url',
                                modifyQueryUsing: fn ($query) => $query->orderBy('url')->limit(500)
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('scraping_status')
                            ->options(ScrapingStatus::class)
                            ->default(ScrapingStatus::PENDING)
                            ->required(),
                        TextInput::make('version')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Stored file')
                    ->schema([
                        TextInput::make('file_path')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('file_size')
                            ->numeric()
                            ->minValue(0)
                            ->label('File size (bytes)'),
                        TextInput::make('file_mime_type')
                            ->maxLength(255),
                        TextInput::make('file_extension')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Metrics')
                    ->schema([
                        TextInput::make('content_length')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('structured_data_count')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('files_count')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('links_count')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('content_change_percentage')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999.99)
                            ->step(0.01)
                            ->suffix('%'),
                    ])
                    ->columns(2),
                Section::make('Cost & debugging')
                    ->schema([
                        TextInput::make('fetch_duration_ms')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ms'),
                        TextInput::make('cost')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->maxValue(9.99),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page.url')
                    ->label('Page URL')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->sortable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('scraping_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('file_path')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('file_size')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('content_length')->sortable(),
                TextColumn::make('structured_data_count')->sortable(),
                TextColumn::make('files_count')->sortable(),
                TextColumn::make('links_count')->sortable(),
                TextColumn::make('content_change_percentage')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fetch_duration_ms')
                    ->label('Fetch (ms)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cost')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
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
                Section::make('Snapshot')
                    ->schema([
                        TextEntry::make('page.url')->label('Page URL')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('version'),
                        TextEntry::make('scraping_status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                        TextEntry::make('file_path')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('file_size')->placeholder('—'),
                        TextEntry::make('file_mime_type')->placeholder('—'),
                        TextEntry::make('file_extension')->placeholder('—'),
                        TextEntry::make('content_length')->placeholder('—'),
                        TextEntry::make('structured_data_count'),
                        TextEntry::make('files_count'),
                        TextEntry::make('links_count'),
                        TextEntry::make('content_change_percentage')->placeholder('—'),
                        TextEntry::make('fetch_duration_ms')->placeholder('—'),
                        TextEntry::make('cost')->placeholder('—'),
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
            'index' => ManageSnapshots::route('/'),
        ];
    }
}
