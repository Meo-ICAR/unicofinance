<?php

namespace App\Filament\Resources\DatoriDiLavoro\Pages;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\DatoriDiLavoro\DatoreDiLavoroResource;

/** La scheda del datore di lavoro è quella del cliente (ridotta ai dati che gli servono). */
class EditDatoreDiLavoro extends EditClient
{
    protected static string $resource = DatoreDiLavoroResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
