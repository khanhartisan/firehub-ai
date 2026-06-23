<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'publications';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel.name')
                    ->label('Channel'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                TextColumn::make('reference')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
