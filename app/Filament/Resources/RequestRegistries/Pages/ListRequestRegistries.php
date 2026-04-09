<?php

namespace App\Filament\Resources\RequestRegistries\Pages;

use App\Filament\Resources\RequestRegistries\RequestRegistryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ListRequestRegistries extends ManageRecords
{
    protected static string $resource = RequestRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
