<?php

namespace App\Filament\Resources\ChecklistItems\Pages;

use App\Filament\Resources\ChecklistItems\ChecklistItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditChecklistItem extends EditRecord
{
    protected static string $resource = ChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
