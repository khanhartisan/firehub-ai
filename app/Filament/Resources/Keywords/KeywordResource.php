<?php

namespace App\Filament\Resources\Keywords;

use App\Filament\Resources\Keywords\Pages\ManageKeywords;
use App\Models\Keyword;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static ?string $recordTitleAttribute = 'keyword';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword')
                    ->required()
                    ->maxLength(255),
                TextInput::make('hash')
                    ->required()
                    ->maxLength(40)
                    ->unique(ignoreRecord: true),
                TextInput::make('global_volume')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('difficulty')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('intents_count')
                    ->numeric()
                    ->minValue(0),
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
                TextColumn::make('global_volume')->sortable(),
                TextColumn::make('difficulty')->sortable(),
                TextColumn::make('intents_count')->sortable(),
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
            'index' => ManageKeywords::route('/'),
        ];
    }
}
