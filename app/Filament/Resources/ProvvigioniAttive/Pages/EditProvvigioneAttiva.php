<?php

namespace App\Filament\Resources\ProvvigioniAttive\Pages;

use App\Filament\Resources\ProvvigioniAttive\ProvvigioniAttiveResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProvvigioneAttiva extends EditRecord
{
    protected static string $resource = ProvvigioniAttiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
