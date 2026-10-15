<?php

namespace App\Filament\Agenti\Resources\Pratiche\Pages;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Filament\Concerns\RegistraCambioStato;
use App\Models\Client;
use App\Services\ContattiSocieta;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Pratica;

class EditPratica extends EditRecord
{
    use RegistraCambioStato;

    protected static string $resource = PraticaResource::class;

    protected static ?string $breadcrumb = 'Perfeziona';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $pratica = $this->getRecord();

        if ($pratica instanceof Pratica) {
            app(PracticeTaskDocuments::class)->attachToPractice($pratica);
        }

        $this->avvisaSeMancaIlFirmatario();
    }

    /** Persona fisica: il cliente stesso. Società: chi riceve le comunicazioni (referente, legale rappresentante, società). */
    private function contatto(?Client $client): ?Client
    {
        if ($client === null || $client->is_person) {
            return $client;
        }

        return app(ContattiSocieta::class)->comunicazioni($client)['client'];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    /** L'OTP della firma va al cellulare del legale rappresentante: senza, la pratica aziendale non può andare in firma. */
    private function avvisaSeMancaIlFirmatario(): void
    {
        $societa = $this->getRecord()->richiedente;

        if ($societa === null || $societa->is_person) {
            return;
        }

        $firmatario = app(ContattiSocieta::class)->firma($societa);

        if ($firmatario === null || blank($firmatario->phone)) {
            Notification::make()
                ->title('Manca il cellulare del legale rappresentante')
                ->body("L'OTP della firma va al suo cellulare: completa i soggetti collegati della società.")
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function origineCambioStato(): string
    {
        return 'agenti';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $client = $this->getRecord()->richiedente;
        $contatto = $this->contatto($client);
        $data['cliente_email'] = $contatto?->email;
        $data['cliente_cellulare'] = $contatto?->phone;
        $data['cliente_iban'] = $client?->iban;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // La banca si sceglie dall'elenco degli istituti: nome e ABI della pratica la seguono.
        if (filled($data['clienti_id'] ?? null)) {
            $banca = Cliente::query()->find($data['clienti_id']);
            $data['denominazione_banca'] = $banca?->name;
            $data['abi'] = $banca?->abi ?: ($data['abi'] ?? null);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->registraCambioStato();

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

        $client->iban = $iban;
        $client->save();

        // Per una società email e cellulare sono del referente (o del legale rappresentante), non della società.
        $contatto = $this->contatto($client) ?? $client;
        $contatto->email = $state['cliente_email'] ?? null;
        $contatto->phone = $state['cliente_cellulare'] ?? null;
        $contatto->save();
    }
}
