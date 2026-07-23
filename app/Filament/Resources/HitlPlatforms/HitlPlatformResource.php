<?php

namespace App\Filament\Resources\HitlPlatforms;

use App\Enums\HitlHook;
use App\Facades\HitlGateway\HitlPlatformManager;
use App\Filament\Resources\HitlPlatforms\Pages\ManageHitlPlatforms;
use App\Filament\Resources\HitlPlatforms\Pages\ViewHitlPlatform;
use App\Filament\Resources\HitlPlatforms\RelationManagers\HitlTasksRelationManager;
use App\Filament\Support\JsonField;
use App\Filament\Support\SchemaFormFieldsFromJsonSchema;
use App\Models\HitlPlatform;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Throwable;

class HitlPlatformResource extends Resource
{
    protected static ?string $model = HitlPlatform::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'HITL Platforms';

    protected static ?string $modelLabel = 'HITL Platform';

    protected static ?string $pluralModelLabel = 'HITL Platforms';

    protected static string|\UnitEnum|null $navigationGroup = 'HITL';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

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
                        Select::make('driver')
                            ->options(collect(array_keys(config('hitlgateway.platform_manager_drivers', [])))
                                ->mapWithKeys(fn (string $driver): array => [$driver => $driver])
                                ->all())
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('config', [])),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(false),
                        CheckboxList::make('hooks')
                            ->options(collect(HitlHook::cases())->mapWithKeys(
                                fn (HitlHook $hook): array => [$hook->value => $hook->name]
                            )->all())
                            ->columns(1)
                            ->columnSpanFull(),
                        JsonField::make('context', 'HITL platform semantic context (JSON).'),
                    ])
                    ->columns(2),
                Section::make('Driver configuration')
                    ->description('Fields are generated from the selected driver config schema.')
                    ->schema(fn (Get $get): array => SchemaFormFieldsFromJsonSchema::make(
                        self::configSchemaForDriver($get('driver')),
                        'config',
                    ))
                    ->visible(fn (Get $get): bool => self::configSchemaForDriver($get('driver')) !== [])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, Type>
     */
    public static function configSchemaForDriver(?string $driver): array
    {
        $driver = trim((string) $driver);

        if ($driver === '' || ! array_key_exists($driver, config('hitlgateway.platform_manager_drivers', []))) {
            return [];
        }

        try {
            $manager = HitlPlatformManager::driver($driver);
            $config = $manager->makeConfig() ?? $manager->getConfig();
        } catch (Throwable) {
            return [];
        }

        if ($config === null) {
            return [];
        }

        /** @var array<string, Type> $schema */
        $schema = $config->toJsonSchema(new JsonSchemaTypeFactory);

        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('driver')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('hooks')
                    ->formatStateUsing(fn ($state): string => collect($state ?? [])
                        ->map(fn ($hook) => $hook instanceof HitlHook ? $hook->name : (string) $hook)
                        ->implode(', '))
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('driver')
                    ->options(collect(array_keys(config('hitlgateway.platform_manager_drivers', [])))
                        ->mapWithKeys(fn (string $driver): array => [$driver => $driver])
                        ->all()),
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
                        TextEntry::make('driver')->badge()->placeholder('—'),
                        IconEntry::make('is_active')->label('Active')->boolean(),
                        TextEntry::make('hooks')
                            ->formatStateUsing(fn ($state): string => collect($state ?? [])
                                ->map(fn ($hook) => $hook instanceof HitlHook ? $hook->name : (string) $hook)
                                ->implode(', '))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            HitlTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHitlPlatforms::route('/'),
            'view' => ViewHitlPlatform::route('/{record}'),
        ];
    }
}
