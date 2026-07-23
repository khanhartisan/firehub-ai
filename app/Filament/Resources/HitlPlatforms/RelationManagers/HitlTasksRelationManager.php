<?php

namespace App\Filament\Resources\HitlPlatforms\RelationManagers;

use App\Contracts\HitlGateway\TaskStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HitlTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'hitlTasks';

    protected static ?string $title = 'HITL Tasks';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TaskStatus
                        ? $state->name
                        : (string) $state)
                    ->sortable(),
                TextColumn::make('internal_reference')
                    ->label('Internal ref')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('hitl_platform_reference')
                    ->label('Platform ref')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
