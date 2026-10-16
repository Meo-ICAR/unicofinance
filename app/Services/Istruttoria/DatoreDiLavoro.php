<?php

namespace App\Services\Istruttoria;

use App\Enums\EmploymentRelationship;
use App\Models\Client;
use App\Models\FinancingQuote;
use App\Models\Pratica;

/**
 * Datore di lavoro (o ente) del cliente nelle pratiche di cessione, delega e prestito. Il datore è un cliente persona giuridica
 * e, quando viene inserito, eredita il tipo di amministrazione della pratica (statale, pubblico, privato SPA...).
 */
class DatoreDiLavoro
{
    private const PRODOTTI = ['Cessione', 'Delega', 'Prestito'];

    /** Il cliente ha una pratica di cessione, delega o prestito: il datore di lavoro è richiesto. */
    public function richiesto(?Client $cliente): bool
    {
        return $cliente?->exists === true && $this->praticaDiCessione($cliente) !== null;
    }

    /** Tipo di amministrazione della pratica di cessione o delega (dal preventivo), altrimenti la tipologia del cliente. */
    public function tipoAmministrazione(Client $cliente): ?string
    {
        $pratica = $this->praticaDiCessione($cliente);
        $tipo = $pratica === null ? null : FinancingQuote::query()->where('pratica_id', $pratica->getKey())->latest('id')->value('employment_relationship');

        return EmploymentRelationship::tryFrom((string) $tipo)?->getLabel() ?: ($cliente->employment_typology ?: null);
    }

    /**
     * Crea il datore di lavoro con il tipo di amministrazione del cliente.
     *
     * @param  array{name: string, tax_code?: ?string}  $dati
     */
    public function crea(?Client $cliente, array $dati, ?string $tipoAmministrazione = null): Client
    {
        return Client::query()->create([
            'name' => $dati['name'],
            'tax_code' => filled($dati['tax_code'] ?? null) ? strtoupper(trim((string) $dati['tax_code'])) : null,
            'is_person' => false,
            'company_id' => $cliente?->company_id,
            'employment_typology' => $tipoAmministrazione ?? ($cliente === null ? null : $this->tipoAmministrazione($cliente)),
        ]);
    }

    /** Il datore indicato sul cliente eredita il tipo di amministrazione, se non ne ha già uno. */
    public function eredita(Client $cliente): void
    {
        $datore = $cliente->employer_id ? Client::query()->find($cliente->employer_id) : null;

        if ($datore === null || filled($datore->employment_typology)) {
            return;
        }

        $tipo = $this->tipoAmministrazione($cliente);

        if ($tipo !== null) {
            $datore->update(['employment_typology' => $tipo]);
        }
    }

    private function praticaDiCessione(Client $cliente): ?Pratica
    {
        return Pratica::query()
            ->where('codice_fiscale', $cliente->tax_code)
            ->whereHas('tipoprodotto', fn ($prodotto) => $prodotto->whereIn('name', self::PRODOTTI))
            ->latest('data_inserimento_pratica')
            ->latest('id')
            ->first();
    }
}
