<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\ManageSources;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static ?string $recordTitleAttribute = 'base_url';

    protected static string|\UnitEnum|null $navigationGroup = 'Remote';

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
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('schedule_scraping')
                    ->label('Schedule scraping')
                    ->default(false),
                TextInput::make('authority_score')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255)
                    ->default(0),
                TextInput::make('priority')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.01)
                    ->default(0.5),

                Section::make('Budgets')
                    ->description('Daily / weekly / monthly scrape budget caps (0 = unlimited).')
                    ->schema([
                        TextInput::make('daily_budget')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0),
                        TextInput::make('weekly_budget')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0),
                        TextInput::make('monthly_budget')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0),
                    ])
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('base_url')->searchable()->sortable()->limit(50),
                IconColumn::make('schedule_scraping')
                    ->boolean()
                    ->label('Scheduled'),
                TextColumn::make('authority_score')->sortable(),
                TextColumn::make('priority')->sortable(),
                TextColumn::make('daily_budget')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('weekly_budget')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('monthly_budget')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
