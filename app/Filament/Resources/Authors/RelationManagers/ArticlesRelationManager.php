<?php

namespace App\Filament\Resources\Authors\RelationManagers;

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
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                TextColumn::make('stage')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
