<?php

namespace App\Filament\Resources\ListinoProvvigionales\Pages;

use App\Filament\Resources\ListinoProvvigionales\ListinoProvvigionaleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditListinoProvvigionale extends EditRecord
{
    protected static string $resource = ListinoProvvigionaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
