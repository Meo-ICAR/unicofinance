<?php

namespace App\Filament\Resources\ProcessTaskPrivacyData\Pages;

use App\Filament\Resources\ProcessTaskPrivacyData\ProcessTaskPrivacyDataResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessTaskPrivacyData extends EditRecord
{
    protected static string $resource = ProcessTaskPrivacyDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
