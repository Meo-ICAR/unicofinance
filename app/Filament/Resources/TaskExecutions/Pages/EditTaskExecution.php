<?php

namespace App\Filament\Resources\TaskExecutions\Pages;

use App\Filament\Resources\TaskExecutions\TaskExecutionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTaskExecution extends EditRecord
{
    protected static string $resource = TaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
