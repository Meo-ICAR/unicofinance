<?php

namespace App\Filament\Agenti\Resources\Clients\Pages;

use App\Filament\Agenti\Resources\Clients\ClientResource;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $breadcrumb = 'Scheda cliente';

    public function getTitle(): string
    {
        return 'Scheda cliente: '.trim(($this->getRecord()->first_name ?? '').' '.($this->getRecord()->name ?? ''));
    }
}
