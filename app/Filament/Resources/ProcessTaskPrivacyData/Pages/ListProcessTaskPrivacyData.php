<?php

namespace App\Filament\Resources\ProcessTaskPrivacyData\Pages;

use App\Filament\Resources\ProcessTaskPrivacyData\ProcessTaskPrivacyDataResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessTaskPrivacyData extends ListRecords
{
    protected static string $resource = ProcessTaskPrivacyDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
