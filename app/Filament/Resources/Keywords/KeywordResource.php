<?php

namespace App\Filament\Resources\Keywords;

use App\Enums\Country;
use App\Enums\KeywordStatus;
use App\Enums\Language;
use App\Filament\Resources\Keywords\Pages\ManageKeywords;
use App\Filament\Resources\Keywords\Pages\ViewKeyword;
use App\Filament\Resources\Keywords\RelationManagers\IntentsRelationManager;
use App\Filament\Support\JsonField;
use App\Models\Keyword;
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

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static ?string $recordTitleAttribute = 'keyword';

    protected static string|\UnitEnum|null $navigationGroup = 'Base';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Keyword')
                    ->schema([
                        TextInput::make('keyword')
                            ->required()
                            ->maxLength(255),
                        Select::make('language')
                            ->options(collect(Language::cases())->mapWithKeys(
                                fn (Language $language): array => [$language->value => $language->value]
                            )->all())
                            ->searchable()
                            ->required(),
                        Select::make('country')
                            ->options(collect(Country::cases())->mapWithKeys(
                                fn (Country $country): array => [$country->value => $country->value]
                            )->all())
                            ->searchable()
                            ->required(),
                        Select::make('status')
                            ->options(collect(KeywordStatus::cases())->mapWithKeys(
                                fn (KeywordStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required(),
                        TextInput::make('volume')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('difficulty')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('intents_count')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        TextInput::make('pages_count')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        TextInput::make('attempts')
                            ->numeric()
                            ->minValue(0),
                        DateTimePicker::make('researched_at')
                            ->seconds(false)
                            ->nullable(),
                        DateTimePicker::make('intent_resolved_at')
                            ->seconds(false)
                            ->nullable(),
                        JsonField::make('search_engine_data', 'Raw search-engine research payload (JSON).'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('language')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('country')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('volume')->sortable(),
                TextColumn::make('difficulty')->sortable(),
                TextColumn::make('intents_count')->sortable()->toggleable(),
                TextColumn::make('pages_count')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attempts')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(KeywordStatus::cases())->mapWithKeys(
                        fn (KeywordStatus $status): array => [$status->value => $status->name]
                    )->all()),
                SelectFilter::make('language')
                    ->options(collect(Language::cases())->mapWithKeys(
                        fn (Language $language): array => [$language->value => $language->value]
                    )->all()),
                SelectFilter::make('country')
                    ->options(collect(Country::cases())->mapWithKeys(
                        fn (Country $country): array => [$country->value => $country->value]
                    )->all())
                    ->searchable(),
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
                Section::make('Keyword')
                    ->schema([
                        TextEntry::make('keyword'),
                        TextEntry::make('hash'),
                        TextEntry::make('language'),
                        TextEntry::make('country'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('volume')->label('Volume'),
                        TextEntry::make('difficulty'),
                        TextEntry::make('intents_count')->label('Intents'),
                        TextEntry::make('pages_count')->label('Pages'),
                        TextEntry::make('attempts'),
                        TextEntry::make('researched_at')->dateTime()->placeholder('—'),
                        TextEntry::make('intent_resolved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('error_logs')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            IntentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKeywords::route('/'),
            'view' => ViewKeyword::route('/{record}'),
        ];
    }
}
