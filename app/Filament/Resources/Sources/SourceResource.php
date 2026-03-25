<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\ManageSources;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static ?string $recordTitleAttribute = 'base_url';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('base_url')
                    ->required()
                    ->url()
                    ->maxLength(2048)
                    ->unique(ignoreRecord: true),
                TextInput::make('authority_score')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0),
                TextInput::make('priority')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.01)
                    ->default(0.5),

                Section::make()->schema([
                    TextInput::make('daily_budget')
                        ->numeric()
                        ->minValue(1)
                        ->step(1)
                        ->default(10),
                    TextInput::make('weekly_budget')
                        ->numeric()
                        ->minValue(1)
                        ->step(1)
                        ->default(100),
                    TextInput::make('monthly_budget')
                        ->numeric()
                        ->minValue(1)
                        ->step(1)
                        ->default(1000),
                ])->columnSpanFull()->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('base_url')->searchable()->sortable()->limit(50),
                TextColumn::make('authority_score')->sortable(),
                TextColumn::make('priority')->sortable(),
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
            'index' => ManageSources::route('/'),
        ];
    }
}
