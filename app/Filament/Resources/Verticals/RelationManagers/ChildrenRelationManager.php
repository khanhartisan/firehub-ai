<?php

namespace App\Filament\Resources\Verticals\RelationManagers;

use App\Filament\Resources\Verticals\VerticalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $relatedResource = VerticalResource::class;

    protected static ?string $relationshipTitle = 'Child verticals';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => [
                        ...$data,
                        'parent_id' => $this->getOwnerRecord()->getKey(),
                    ]),
            ]);
    }
}
