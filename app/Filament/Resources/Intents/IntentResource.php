<?php

namespace App\Filament\Resources\Intents;

use App\Enums\IntentType;
use App\Enums\Language;
use App\Filament\Resources\Intents\Pages\ManageIntents;
use App\Models\Intent;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntentResource extends Resource
{
    protected static ?string $model = Intent::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

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
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('language')
                    ->sortable(),
                TextColumn::make('types')
                    ->formatStateUsing(fn ($state): string => collect($state ?? [])->implode(', '))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('keywords_count')->sortable(),
                TextColumn::make('pages_count')->sortable(),
                TextColumn::make('articles_count')->sortable(),
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
            'index' => ManageIntents::route('/'),
        ];
    }
}
