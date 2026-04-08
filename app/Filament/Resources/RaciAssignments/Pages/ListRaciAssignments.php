<?php

namespace App\Filament\Resources\RaciAssignments\Pages;

use App\Filament\Resources\RaciAssignments\RaciAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRaciAssignments extends ListRecords
{
    protected static string $resource = RaciAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
