<?php

namespace App\Filament\Resources\Intents\RelationManagers;

use App\Filament\Resources\Keywords\KeywordResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeywordsRelationManager extends RelationManager
{
    protected static string $relationship = 'keywords';

    protected static ?string $relatedResource = KeywordResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('global_volume')
                    ->sortable(),
                TextColumn::make('difficulty')
                    ->sortable(),
                TextColumn::make('intent_keyword.relevance')
                    ->label('Relevance')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
            ]);
    }
}
