<?php

namespace App\Filament\Resources\Files;

use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Filament\Resources\Files\Pages\ManageFiles;
use App\Models\File;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $recordTitleAttribute = 'url';

    protected static string|\UnitEnum|null $navigationGroup = 'Remote';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('File')
                    ->schema([
                        Select::make('scraping_status')
                            ->options(ScrapingStatus::class)
                            ->default(ScrapingStatus::PENDING)
                            ->required(),
                        Select::make('scraping_stage')
                            ->options(collect(ScrapingStage::cases())->mapWithKeys(
                                fn (ScrapingStage $stage): array => [$stage->value => $stage->value]
                            )->all())
                            ->searchable(),
                        TextInput::make('url')
                            ->required()
                            ->url()
                            ->maxLength(65535),
                        TextInput::make('url_hash')
                            ->required()
                            ->maxLength(40)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Storage & fetch')
                    ->schema([
                        TextInput::make('path')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('mime_type')
                            ->maxLength(255),
                        TextInput::make('extension')
                            ->maxLength(50),
                        TextInput::make('size')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('fetch_duration_ms')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ms'),
                        TextInput::make('attempts')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('cost')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('fileables_count')
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('error_logs')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->searchable()
                    ->sortable()
                    ->limit(45)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('scraping_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('scraping_stage')
                    ->toggleable(),
                TextColumn::make('extension')->sortable(),
                TextColumn::make('size')->numeric()->sortable(),
                TextColumn::make('attempts')->sortable(),
                TextColumn::make('fileables_count')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFiles::route('/'),
        ];
    }
}
