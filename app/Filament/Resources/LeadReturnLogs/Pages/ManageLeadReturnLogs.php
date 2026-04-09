<?php

namespace App\Filament\Resources\LeadReturnLogs\Pages;

use App\Filament\Resources\LeadReturnLogs\LeadReturnLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeadReturnLogs extends ManageRecords
{
    protected static string $resource = LeadReturnLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
