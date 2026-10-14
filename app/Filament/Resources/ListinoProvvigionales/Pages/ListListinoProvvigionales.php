<?php

namespace App\Filament\Resources\ListinoProvvigionales\Pages;

use App\Filament\Resources\ListinoProvvigionales\ListinoProvvigionaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListinoProvvigionales extends ListRecords
{
    protected static string $resource = ListinoProvvigionaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
