<?php

namespace App\Filament\Resources\ConsentLogs\Pages;

use App\Filament\Resources\ConsentLogs\ConsentLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageConsentLogs extends ManageRecords
{
    protected static string $resource = ConsentLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
