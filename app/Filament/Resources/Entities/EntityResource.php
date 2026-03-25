<?php

namespace App\Filament\Resources\Entities;

use App\Enums\EntityType;
use App\Enums\ScrapingStatus;
use App\Filament\Resources\Entities\Pages\ManageEntities;
use App\Models\Entity;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static ?string $recordTitleAttribute = 'url';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('source_id')
                    ->relationship('source', 'base_url')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('url')
                    ->required()
                    ->url()
                    ->maxLength(65535),
                Textarea::make('description')->maxLength(1024)->columnSpanFull(),
                Select::make('type')
                    ->options(EntityType::class)
                    ->required(),
                Select::make('scraping_status')
                    ->options(ScrapingStatus::class)
                    ->default(ScrapingStatus::PENDING),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source.base_url')->limit(40)->sortable(),
                TextColumn::make('url')->limit(50)->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('scraping_status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state),
                TextColumn::make('scraped_at')->dateTime()->sortable(),
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
            'index' => ManageEntities::route('/'),
        ];
    }
}
