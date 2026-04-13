<?php

namespace App\Filament\Resources\Keywords\RelationManagers;

use App\Enums\IntentType;
use App\Filament\Resources\Intents\IntentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntentsRelationManager extends RelationManager
{
    protected static string $relationship = 'intents';

    protected static ?string $relatedResource = IntentResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('language')
                    ->sortable(),
                TextColumn::make('types')
                    ->formatStateUsing(fn ($state): string => collect($state ?? [])->map(
                        fn (IntentType $intentType) => $intentType->name
                    )->implode(', '))
                    ->toggleable(),
                TextColumn::make('intent_keyword.relevance')
                    ->label('Relevance')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
