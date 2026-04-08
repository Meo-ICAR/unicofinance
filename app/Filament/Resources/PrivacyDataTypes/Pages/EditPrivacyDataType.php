<?php

namespace App\Filament\Resources\PrivacyDataTypes\Pages;

use App\Filament\Resources\PrivacyDataTypes\PrivacyDataTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrivacyDataType extends EditRecord
{
    protected static string $resource = PrivacyDataTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
