<?php

namespace App\Filament\Resources\Clients;

use App\Enums\Language;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ArticlesRelationManager;
use App\Filament\Resources\Clients\RelationManagers\AuthorsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\ChannelsRelationManager;
use App\Filament\Support\SemanticContextForm;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenants';

    protected static ?int $navigationSort = 100;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client')
                    ->schema([
                        TextInput::make('name')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('language')
                            ->options(collect(Language::cases())->mapWithKeys(
                                fn (Language $language): array => [$language->value => $language->value]
                            )->all())
                            ->searchable()
                            ->nullable(),
                        Select::make('hitl_platform_id')
                            ->label('HITL Platform')
                            ->relationship('hitlPlatform', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('channels_count')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ])
                    ->columns(2),
                ...SemanticContextForm::components(
                    ClientContext::class,
                    heading: 'Brand context',
                    description: 'Client brand semantic context used across content generation.',
                ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('language')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('hitlPlatform.name')
                    ->label('HITL Platform')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('channels_count')
                    ->label('Channels')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('name')->placeholder('—'),
                        TextEntry::make('language')->placeholder('—'),
                        TextEntry::make('hitlPlatform.name')->label('HITL Platform')->placeholder('—'),
                        TextEntry::make('channels_count')->label('Channels'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuthorsRelationManager::class,
            ChannelsRelationManager::class,
            ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
            'view' => ViewClient::route('/{record}'),
        ];
    }
}
