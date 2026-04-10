<?php

namespace App\Filament\Resources\Processes\Pages;

use App\Filament\Resources\Processes\ProcessesResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcesses extends ListRecords
{
    protected static string $resource = ProcessesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('create-wizard')
                ->label('Crea Processo Completo')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url(fn(): string => ProcessesResource::getUrl('create-wizard'))
            //  ->visible(fn (): bool => auth()->user()?->isTenantAdmin() ?? false),
        ];
    }
}
