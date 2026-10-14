<?php

namespace App\Filament\Agenti\Resources\Pratiche\Pages;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\Client;
use Filament\Resources\Pages\EditRecord;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Models\Pratica;

class EditPratica extends EditRecord
{
    protected static string $resource = PraticaResource::class;

    protected static ?string $breadcrumb = 'Perfeziona';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $pratica = $this->getRecord();

        if ($pratica instanceof Pratica) {
            app(PracticeTaskDocuments::class)->attachToPractice($pratica);
        }
    }

    public function getTitle(): string
    {
        return 'Perfeziona '.$this->getRecordTitle();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $client = $this->getRecord()->richiedente;
        $data['cliente_email'] = $client?->email;
        $data['cliente_cellulare'] = $client?->phone;
        $data['cliente_iban'] = $client?->iban;

        return $data;
    }

    protected function afterSave(): void
    {
        $pratica = $this->getRecord();
        $state = $this->form->getRawState();
        $iban = filled($state['cliente_iban'] ?? null)
            ? strtoupper(str_replace(' ', '', (string) $state['cliente_iban']))
            : null;

        $client = $pratica->richiedente;

        if ($client === null && filled($pratica->codice_fiscale)) {
            $taxCode = strtoupper(trim((string) $pratica->codice_fiscale));
            $client = Client::query()->firstOrNew(['tax_code' => $taxCode]);

            if (! $client->exists) {
                $client->name = $pratica->cognome_cliente ?: 'Cliente';
                $client->first_name = $pratica->nome_cliente;
                $client->is_person = strlen($taxCode) !== 11;
                $client->company_id = $pratica->company_id;
            }
        }

        if ($client === null) {
            return;
        }

        $client->email = $state['cliente_email'] ?? null;
        $client->phone = $state['cliente_cellulare'] ?? null;
        $client->iban = $iban;
        $client->save();
    }
}
