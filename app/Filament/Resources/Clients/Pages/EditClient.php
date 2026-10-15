<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\FinancingSection;
use App\Enums\FinancingType;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Services\Istruttoria\DatoreDiLavoro;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /** Famiglia di finanziamento dell'ultima pratica del cliente; senza pratiche si vede tutto. */
    public function financingType(): ?FinancingType
    {
        /** @var Client $client */
        $client = $this->getRecord();
        $pratica = $client->ultimaPratica;

        return $pratica === null ? null : FinancingType::forPratica($pratica);
    }

    public function showsFinancingSection(FinancingSection $section): bool
    {
        return $section->isVisibleFor($this->financingType());
    }

    public function getSubheading(): ?string
    {
        /** @var Client $client */
        $client = $this->getRecord();
        $type = $this->financingType();

        if ($type === null) {
            return null;
        }

        return "Ultima pratica {$client->ultimaPratica->codice_pratica} · {$type->getLabel()}: sono mostrati solo i dati pertinenti.";
    }

    protected function afterSave(): void
    {
        app(DatoreDiLavoro::class)->eredita($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
