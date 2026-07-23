<?php

namespace App\Filament\Resources\Articles;

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Resources\Articles\Pages\ViewArticle;
use App\Filament\Resources\Articles\RelationManagers\PublicationsRelationManager;
use App\Filament\Support\JsonField;
use App\Filament\Support\SemanticContextForm;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Models\Article;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('author_id')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('status')
                            ->options(collect(ArticleStatus::cases())->mapWithKeys(
                                fn (ArticleStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required(),
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
                        DateTimePicker::make('processing_at')
                            ->seconds(false)
                            ->nullable(),
                        Select::make('thumbnail_file_id')
                            ->label('Thumbnail file')
                            ->relationship('thumbnailFile', 'url')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('intents_count')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('attempts')
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
                        JsonField::make('article', 'JSON DOM payload for the article body.', 14),
                        JsonField::make('illustration', 'Illustration payload (JSON).', 8),
                        JsonField::make('stage_data', 'Pipeline stage data (JSON).', 8),
                    ])
                    ->columns(2),
                ...SemanticContextForm::components(
                    ArticleContext::class,
                    heading: 'Article context',
                    description: 'Article-level semantic context for generation and HITL flows.',
                ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(80)
                    ->description(fn (Article $article): ?string => $article->excerpt),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('stage')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('stage_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('language')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('intents_count')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_embedded')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ArticleStatus::cases())->mapWithKeys(
                        fn (ArticleStatus $status): array => [$status->value => $status->name]
                    )->all()),
                SelectFilter::make('client_id')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('stage')
                    ->options(collect(ArticleStage::cases())->mapWithKeys(
                        fn (ArticleStage $stage): array => [$stage->value => $stage->name]
                    )->all()),
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
                Section::make('Article')
                    ->schema([
                        TextEntry::make('client.name')->label('Client')->placeholder('—'),
                        TextEntry::make('author.name')->label('Author')->placeholder('—'),
                        TextEntry::make('title')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('excerpt')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('stage')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('stage_status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('language')->placeholder('—'),
                        TextEntry::make('temporal')->placeholder('—'),
                        TextEntry::make('intents_count')->label('Intents'),
                        TextEntry::make('attempts'),
                        TextEntry::make('intent_resolved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('processing_at')->dateTime()->placeholder('—'),
                        TextEntry::make('thumbnailFile.url')->label('Thumbnail')->placeholder('—'),
                        TextEntry::make('is_embeddable')->boolean(),
                        TextEntry::make('is_embedded')->boolean(),
                        TextEntry::make('error_logs')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PublicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageArticles::route('/'),
            'view' => ViewArticle::route('/{record}'),
        ];
    }
}
