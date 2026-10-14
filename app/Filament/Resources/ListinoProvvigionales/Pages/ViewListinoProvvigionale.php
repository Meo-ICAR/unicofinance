<?php

namespace App\Filament\Resources\ListinoProvvigionales\Pages;

use App\Filament\Resources\ListinoProvvigionales\ListinoProvvigionaleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewListinoProvvigionale extends ViewRecord
{
    protected static string $resource = ListinoProvvigionaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
