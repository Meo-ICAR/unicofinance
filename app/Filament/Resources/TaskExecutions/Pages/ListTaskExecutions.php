<?php

namespace App\Filament\Resources\TaskExecutions\Pages;

use App\Filament\Resources\TaskExecutions\TaskExecutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskExecutions extends ListRecords
{
    protected static string $resource = TaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
