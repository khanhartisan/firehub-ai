<?php

namespace App\Filament\Resources\Channels;

use App\Enums\ChannelStatus;
use App\Filament\Resources\Channels\Pages\ManageChannels;
use App\Filament\Resources\Channels\Pages\ViewChannel;
use App\Filament\Resources\Channels\RelationManagers\PublicationsRelationManager;
use App\Filament\Support\JsonField;
use App\Models\Channel;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?int $navigationSort = 250;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Channel')
                    ->schema([
                        Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('platform_id')
                            ->relationship('platform', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(collect(ChannelStatus::cases())->mapWithKeys(
                                fn (ChannelStatus $status): array => [$status->value => $status->name]
                            )->all())
                            ->required(),
                        TextInput::make('reference')
                            ->maxLength(255),
                        TextInput::make('publications_count')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        JsonField::make('config', 'Channel-specific configuration (JSON).'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('platform.name')
                    ->label('Platform')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
                TextColumn::make('publications_count')
                    ->label('Publications')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ChannelStatus::cases())->mapWithKeys(
                        fn (ChannelStatus $status): array => [$status->value => $status->name]
                    )->all()),
                SelectFilter::make('client_id')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('platform_id')
                    ->relationship('platform', 'name')
                    ->searchable()
                    ->preload(),
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
                Section::make('Channel')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('name'),
                        TextEntry::make('client.name')->label('Client')->placeholder('—'),
                        TextEntry::make('platform.name')->label('Platform'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
                        TextEntry::make('reference')->placeholder('—'),
                        TextEntry::make('publications_count')->label('Publications'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PublicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageChannels::route('/'),
            'view' => ViewChannel::route('/{record}'),
        ];
    }
}
