<?php

namespace App\Filament\Resources\ProcessTasks\RelationManagers;

use App\Filament\Resources\RaciAssignments\RaciAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RaciAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'raciAssignments';

    protected static ?string $relatedResource = RaciAssignmentResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
