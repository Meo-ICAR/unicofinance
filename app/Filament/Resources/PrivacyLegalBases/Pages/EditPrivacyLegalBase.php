<?php

namespace App\Filament\Resources\PrivacyLegalBases\Pages;

use App\Filament\Resources\PrivacyLegalBases\PrivacyLegalBaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrivacyLegalBase extends EditRecord
{
    protected static string $resource = PrivacyLegalBaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
