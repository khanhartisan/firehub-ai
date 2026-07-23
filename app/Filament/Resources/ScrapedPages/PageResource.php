<?php

namespace App\Filament\Resources\ScrapedPages;

use App\Enums\ContentType;
use App\Enums\Language;
use App\Enums\PageType;
use App\Enums\ScrapableType;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Filament\Resources\ScrapedPages\Pages\ManagePages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $recordTitleAttribute = 'url';

    protected static ?string $navigationLabel = 'Pages';

    protected static string|\UnitEnum|null $navigationGroup = 'Resources';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->schema([
                        Select::make('source_id')
                            ->relationship('source', 'base_url')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('url')
                            ->required()
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->maxLength(1024)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->maxLength(1024)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Classification')
                    ->schema([
                        Select::make('type')
                            ->options(ScrapableType::class)
                            ->required(),
                        Select::make('page_type')
                            ->options(PageType::class)
                            ->nullable(),
                        Select::make('content_type')
                            ->options(ContentType::class)
                            ->nullable(),
                        Select::make('temporal')
                            ->options(Temporal::class)
                            ->nullable(),
                        Select::make('language')
                            ->options(Language::class)
                            ->searchable()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Scraping')
                    ->schema([
                        Select::make('scraping_status')
                            ->options(ScrapingStatus::class)
                            ->default(ScrapingStatus::PENDING),
                        Select::make('scraping_stage')
                            ->options(ScrapingStage::class)
                            ->nullable(),
                        Toggle::make('ignore_scraping_budget')
                            ->default(false),
                        TextInput::make('version_index')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('attempts')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        DateTimePicker::make('next_scrape_at')
                            ->seconds(false)
                            ->nullable(),
                        DateTimePicker::make('scraped_at')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Source timestamps')
                    ->schema([
                        DateTimePicker::make('source_published_at')
                            ->seconds(false)
                            ->nullable(),
                        DateTimePicker::make('source_updated_at')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Canonical')
                    ->schema([
                        Select::make('canonical_page_id')
                            ->label('Canonical page')
                            ->relationship('canonicalPage', 'url', null, true)
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('canonical_number')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->limit(50)
                    ->description(function (Page $page) {
                        return Str::limit($page->title ?: $page->description, 50);
                    })
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('scraping_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('next_scrape_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('scraped_at')->dateTime()->sortable(),
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
            'index' => ManagePages::route('/'),
        ];
    }
}
