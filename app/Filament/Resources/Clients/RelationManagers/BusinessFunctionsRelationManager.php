<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\BusinessFunctions\BusinessFunctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class BusinessFunctionsRelationManager extends RelationManager
{
    protected static string $relationship = 'businessFunctions';

    protected static ?string $relatedResource = BusinessFunctionResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
