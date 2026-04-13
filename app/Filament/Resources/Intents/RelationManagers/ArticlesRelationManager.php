<?php

namespace App\Filament\Resources\Intents\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client_id')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('stage')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                TextColumn::make('article_intent.relevance')
                    ->label('Relevance')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
