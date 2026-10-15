<?php

namespace App\Services\Istruttoria;

use App\Models\Client;
use App\Models\Pratica;
use Illuminate\Support\Str;

/** Garantisce che il richiedente di una pratica abbia l'anagrafica cliente, collegata dal codice fiscale o dalla partita IVA. */
class RichiedentePratica
{
    public function ensureClient(Pratica $pratica): ?Client
    {
        $code = Str::upper(trim((string) $pratica->codice_fiscale));

        if ($code === '') {
            return null;
        }

        $existing = Client::query()
            ->where(fn ($query) => $query->where('tax_code', $code)->orWhere('vat_number', $code))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $isPerson = ! preg_match('/^(IT)?\d{11}$/', $code);

        return Client::query()->create([
            'company_id' => $pratica->company_id,
            'is_person' => $isPerson,
            'name' => $pratica->cognome_cliente ?: $code,
            'first_name' => $isPerson ? $pratica->nome_cliente : null,
            'tax_code' => $code,
        ]);
    }
}
