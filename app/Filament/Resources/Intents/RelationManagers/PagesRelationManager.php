<?php

namespace App\Filament\Resources\Intents\RelationManagers;

use App\Filament\Resources\ScrapedPages\PageResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesRelationManager extends RelationManager
{
    protected static string $relationship = 'pages';

    protected static ?string $relatedResource = PageResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('title')
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('scraping_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('intent_page.relevance')
                    ->label('Relevance')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
            ]);
    }
}
