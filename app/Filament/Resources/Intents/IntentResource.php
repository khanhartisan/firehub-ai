<?php

namespace App\Filament\Resources\Intents;

use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Filament\Resources\Intents\Pages\ManageIntents;
use App\Filament\Resources\Intents\Pages\ViewIntent;
use App\Filament\Resources\Intents\RelationManagers\ArticlesRelationManager;
use App\Filament\Resources\Intents\RelationManagers\KeywordsRelationManager;
use App\Filament\Resources\Intents\RelationManagers\PagesRelationManager;
use App\Models\Intent;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntentResource extends Resource
{
    protected static ?string $model = Intent::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Base';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Intent')
                    ->schema([
                        Select::make('language')
                            ->options(collect(Language::cases())->mapWithKeys(
                                fn (Language $language): array => [$language->value => $language->value]
                            )->all())
                            ->searchable(),
                        Select::make('temporal')
                            ->options(Temporal::class)
                            ->nullable(),
                        TextInput::make('title')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                        CheckboxList::make('types')
                            ->options(collect(IntentType::cases())->mapWithKeys(
                                fn (IntentType $type): array => [$type->value => $type->name]
                            )->all())
                            ->columns(2)
                            ->columnSpanFull(),
                        Toggle::make('is_embeddable'),
                        Toggle::make('is_embedded'),
                    ])
                    ->columns(2),
                Section::make('Related counters')
                    ->schema([
                        TextInput::make('keywords_count')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('pages_count')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('articles_count')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->width(350)
                    ->wrap()
                    ->wrapHeader()
                    ->description(fn (Intent $intent) => $intent->description)
                    ->searchable(),
                TextColumn::make('language')
                    ->sortable(),
                TextColumn::make('temporal')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('types')
                    ->formatStateUsing(fn ($state): string => collect($state ?? [])->map(fn (IntentType $type) => $type->name)->implode(', '))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('counts')
                    ->label('Counts')
                    ->state(fn (Intent $intent): string => sprintf(
                        'K:%d P:%d A:%d',
                        $intent->keywords_count ?? 0,
                        $intent->pages_count ?? 0,
                        $intent->articles_count ?? 0
                    ))
                    ->tooltip(fn (Intent $intent): string => sprintf(
                        'Keywords: %d, Pages: %d, Articles: %d',
                        $intent->keywords_count ?? 0,
                        $intent->pages_count ?? 0,
                        $intent->articles_count ?? 0
                    )),
                IconColumn::make('is_embedded')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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
                Section::make('Intent')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('language'),
                        TextEntry::make('temporal')->placeholder('—'),
                        TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('types')
                            ->formatStateUsing(fn ($state): string => collect($state ?? [])->map(fn (IntentType $type) => $type->name)->implode(', ')),
                        IconEntry::make('is_embeddable')->boolean(),
                        IconEntry::make('is_embedded')->boolean(),
                        TextEntry::make('keywords_count')->label('Keywords'),
                        TextEntry::make('pages_count')->label('Pages'),
                        TextEntry::make('articles_count')->label('Articles'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            KeywordsRelationManager::class,
            ArticlesRelationManager::class,
            PagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIntents::route('/'),
            'view' => ViewIntent::route('/{record}'),
        ];
    }
}
