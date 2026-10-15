<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Filament\Resources\Praticas\PraticaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraticas extends ListRecords
{
    protected static string $resource = PraticaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
