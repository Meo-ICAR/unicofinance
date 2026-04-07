<?php

namespace App\Filament\Resources\ChecklistItems\Pages;

use App\Filament\Resources\ChecklistItems\ChecklistItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChecklistItems extends ListRecords
{
    protected static string $resource = ChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
