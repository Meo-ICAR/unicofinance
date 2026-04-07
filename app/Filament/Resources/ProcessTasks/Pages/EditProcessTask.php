<?php

namespace App\Filament\Resources\ProcessTasks\Pages;

use App\Filament\Resources\ProcessTasks\ProcessTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessTask extends EditRecord
{
    protected static string $resource = ProcessTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
