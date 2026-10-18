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
     * Un codice fiscale è utilizzabile da un agente solo se tutte le pratiche con quel codice sono sue: altrimenti l'agente
     * vedrebbe (e userebbe) dati di clienti di altri. Senza pratiche, solo se il cliente non è già in anagrafica.
     */
    public function isUsableByAgent(string $taxCode, Fornitore $agent): bool
    {
        $code = Str::upper(trim($taxCode));
        $pratiche = Pratica::query()->where('codice_fiscale', $code)->get(['fornitori_id', 'partita_iva_agente']);

        if ($pratiche->isEmpty()) {
            return Client::query()->where('tax_code', $code)->orWhere('vat_number', $code)->doesntExist();
        }

        return $pratiche->every(fn (Pratica $pratica): bool => (string) $pratica->fornitori_id === (string) $agent->getKey()
            || filled($agent->piva) && trim((string) $pratica->partita_iva_agente) === trim((string) $agent->piva));
    }

    public function findOrCreate(string $taxCode, string $name, ?string $firstName, string $email, string $phone, mixed $companyId): Client
    {
        $code = Str::upper(trim($taxCode));

        $existing = Client::query()->where('tax_code', $code)->orWhere('vat_number', $code)->first();

        if ($existing !== null) {
            return $existing;
        }

        // Undici cifre sono una partita IVA: il cliente è una società.
        $isCompany = preg_match('/^\d{11}$/', $code) === 1;

        return Client::query()->forceCreate([
            'company_id' => $companyId,
            'is_person' => ! $isCompany,
            'is_company' => $isCompany,
            'is_client' => true,
            'name' => trim($name),
            'first_name' => $isCompany ? null : trim((string) $firstName),
            'tax_code' => $code,
            'vat_number' => $isCompany ? $code : null,
            'email' => Str::lower(trim($email)),
            'phone' => trim($phone),
        ]);
    }
}
