<?php

namespace App\Filament\Resources\Platforms;

use App\Enums\PlatformType;
use App\Filament\Resources\Platforms\Pages\ManagePlatforms;
use App\Filament\Resources\Platforms\Pages\ViewPlatform;
use App\Filament\Resources\Platforms\RelationManagers\ChannelsRelationManager;
use App\Filament\Support\JsonField;
use App\Models\Platform;
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

class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?int $navigationSort = 150;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Platform')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('type')
                            ->options(collect(PlatformType::cases())->mapWithKeys(
                                fn (PlatformType $type): array => [$type->value => $type->name]
                            )->all())
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('channels_count')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        JsonField::make('config', 'Platform configuration (JSON).'),
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
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state)
                    ->sortable(),
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
                Section::make('Platform')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('name'),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->name ?? (string) $state),
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
            ChannelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlatforms::route('/'),
            'view' => ViewPlatform::route('/{record}'),
        ];
    }
}
