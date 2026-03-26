<?php

namespace App\Filament\Resources\BusinessFunctions\Pages;

use App\Filament\Resources\BusinessFunctions\BusinessFunctionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBusinessFunction extends EditRecord
{
    protected static string $resource = BusinessFunctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
