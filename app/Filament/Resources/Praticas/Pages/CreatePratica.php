<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Services\Istruttoria\RichiedentePratica;
use Filament\Resources\Pages\CreateRecord;

class CreatePratica extends CreateRecord
{
    protected static string $resource = PraticaResource::class;

    protected function afterCreate(): void
    {
        app(RichiedentePratica::class)->ensureClient($this->getRecord());
    }
}
