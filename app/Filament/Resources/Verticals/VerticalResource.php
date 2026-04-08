<?php

namespace App\Filament\Resources\Verticals;

use App\Filament\Resources\Verticals\Pages\ManageVerticals;
use App\Models\Vertical;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerticalResource extends Resource
{
    protected static ?string $model = Vertical::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('parent_id')
                    ->label('Parent vertical')
                    ->relationship('parent', 'name', null, true)
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('is_embeddable')
                    ->label('Embeddable')
                    ->default(false),
                Toggle::make('is_embedded')
                    ->label('Embedded')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable(),
                TextColumn::make('description')->limit(50),
                IconColumn::make('is_embeddable')
                    ->label('Embeddable')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_embedded')
                    ->label('Embedded')
                    ->boolean()
                    ->sortable(),
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
            'index' => ManageVerticals::route('/'),
        ];
    }
}
