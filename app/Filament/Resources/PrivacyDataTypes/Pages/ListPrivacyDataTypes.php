<?php

namespace App\Filament\Resources\PrivacyDataTypes\Pages;

use App\Filament\Resources\PrivacyDataTypes\PrivacyDataTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyDataTypes extends ListRecords
{
    protected static string $resource = PrivacyDataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
