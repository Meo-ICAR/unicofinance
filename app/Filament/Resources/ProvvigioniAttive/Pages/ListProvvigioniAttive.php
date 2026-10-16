<?php

namespace App\Filament\Resources\ProvvigioniAttive\Pages;

use App\Filament\Resources\ProvvigioniAttive\ProvvigioniAttiveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProvvigioniAttive extends ListRecords
{
    protected static string $resource = ProvvigioniAttiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
