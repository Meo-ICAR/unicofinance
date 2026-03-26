<?php

namespace App\Filament\Resources\TaskExecutions\Pages;

use App\Filament\Resources\TaskExecutions\TaskExecutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTaskExecutions extends ManageRecords
{
    protected static string $resource = TaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
