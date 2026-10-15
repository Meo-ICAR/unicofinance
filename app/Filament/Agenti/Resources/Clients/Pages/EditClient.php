<?php

namespace App\Filament\Agenti\Resources\Clients\Pages;

use App\Filament\Agenti\Resources\Clients\ClientResource;
use App\Services\Istruttoria\DatoreDiLavoro;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $breadcrumb = 'Scheda cliente';

    protected function afterSave(): void
    {
        app(DatoreDiLavoro::class)->eredita($this->getRecord());
    }

    public function getTitle(): string
    {
        return 'Scheda cliente: '.trim(($this->getRecord()->first_name ?? '').' '.($this->getRecord()->name ?? ''));
    }
}
