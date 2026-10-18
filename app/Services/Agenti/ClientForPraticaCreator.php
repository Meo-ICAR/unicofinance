<?php

namespace App\Services\Agenti;

use App\Models\Client;
use App\Models\Pratica;
use Illuminate\Support\Str;
use Unico\Core\Models\Fornitore;

/**
 * Cliente in anagrafica per una pratica consegnata da un agente: si riusa quello con lo stesso codice fiscale o partita IVA
 * (senza toccarne i dati), altrimenti se ne crea uno con i soli dati minimi.
 */
class ClientForPraticaCreator
{
    /**
     * Un cliente già in anagrafica è utilizzabile da un agente solo se tutte le pratiche con quel codice fiscale sono sue:
     * altrimenti l'agente vedrebbe (e userebbe) dati di clienti di altri.
     */
    public function isUsableByAgent(string $taxCode, Fornitore $agent): bool
    {
        $code = Str::upper(trim($taxCode));

        if (Client::query()->where('tax_code', $code)->orWhere('vat_number', $code)->doesntExist()) {
            return true;
        }

        $pratiche = Pratica::query()->where('codice_fiscale', $code)->get(['fornitori_id', 'partita_iva_agente']);

        return $pratiche->isNotEmpty() && $pratiche->every(fn (Pratica $pratica): bool => (string) $pratica->fornitori_id === (string) $agent->getKey()
            || filled($agent->piva) && trim((string) $pratica->partita_iva_agente) === trim((string) $agent->piva));
    }

    public function findOrCreate(string $taxCode, string $name, ?string $firstName, string $email, string $phone, mixed $companyId): Client
    {
        $code = Str::upper(trim($taxCode));

        $existing = Client::query()->where('tax_code', $code)->orWhere('vat_number', $code)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Client::query()->forceCreate([
            'company_id' => $companyId,
            'is_person' => true,
            'is_company' => false,
            'is_client' => true,
            'name' => trim($name),
            'first_name' => trim((string) $firstName),
            'tax_code' => $code,
            'email' => Str::lower(trim($email)),
            'phone' => trim($phone),
        ]);
    }
}
