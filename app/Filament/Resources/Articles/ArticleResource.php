<?php

namespace App\Filament\Resources\Articles;

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Models\Article;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?int $navigationSort = 400;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->schema([
                        TextInput::make('client_id')
                            ->required()
                            ->maxLength(255),
                        Select::make('language')
                            ->options(collect(Language::cases())->mapWithKeys(
                                fn (Language $language): array => [$language->value => $language->value]
                            )->all())
                            ->searchable()
                            ->nullable(),
                        Select::make('temporal')
                            ->options(Temporal::class)
                            ->nullable(),
                        Select::make('stage')
                            ->options(collect(ArticleStage::cases())->mapWithKeys(
                                fn (ArticleStage $stage): array => [$stage->value => $stage->name]
                            )->all())
                            ->required(),
                        Select::make('stage_status')
                            ->options(collect(ArticleStageStatus::cases())->mapWithKeys(
                                fn (ArticleStageStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required(),
                        DateTimePicker::make('intent_resolved_at')
                            ->seconds(false)
                            ->nullable(),
                        TextInput::make('thumbnail_file_id')
                            ->maxLength(26),
                        TextInput::make('intents_count')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('is_embeddable'),
                        Toggle::make('is_embedded'),
                        Textarea::make('title')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('excerpt')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('prompt')
                            ->rows(8)
                            ->columnSpanFull(),
                        Textarea::make('body_markdown')
                            ->rows(14)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client_id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(80)
                    ->description(fn (Article $article): ?string => $article->excerpt),
                TextColumn::make('stage')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('stage_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('language')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('temporal')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('intents_count')
                    ->sortable(),
                IconColumn::make('is_embedded')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
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
            'index' => ManageArticles::route('/'),
        ];
    }
}
