<?php

namespace App\Filament\Resources\PrivacyLegalBases\Pages;

use App\Filament\Resources\PrivacyLegalBases\PrivacyLegalBaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyLegalBases extends ListRecords
{
    protected static string $resource = PrivacyLegalBaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
