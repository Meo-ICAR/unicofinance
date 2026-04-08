<?php

namespace App\Filament\Resources\ProcessTasks\RelationManagers;

use App\Filament\Resources\ProcessTaskPrivacyData\ProcessTaskPrivacyDataResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PrivacyDataRelationManager extends RelationManager
{
    protected static string $relationship = 'privacyData';

    protected static ?string $relatedResource = ProcessTaskPrivacyDataResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
