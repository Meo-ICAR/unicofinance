<?php

namespace App\Services\Preventivazione;

use App\Enums\SimulationContractType;
use App\Models\FinancingQuote;
use App\Models\User;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

/** Crea la pratica in stato perfezionato e collega il preventivo. */
class PracticeFinalizer
{
    public function __construct(private FinancingQuoteRecorder $quotes) {}

    /**
     * @param  array<string, mixed>  $profile
     * @param  array{min: float, max: float}  $quotation
     */
    public function finalize(Fornitore $fornitore, array $profile, array $quotation, User $user): FinancingQuote
    {
        $contract = SimulationContractType::from((string) $profile['contract_type']);
        $productName = $contract->productName();

        $tipo = TipoProdotto::query()->where('name', $productName)->first();
        $stato = PraticaStato::query()
            ->where('codice', 'PERFEZIONATA')
            ->where('tipo_finanziamento', $productName)
            ->first();

        if ($tipo === null || $stato === null) {
            throw new QuoteUnavailable("Non trovo il prodotto o lo stato perfezionato per {$productName}.");
        }

        $pratica = Pratica::query()->create([
            'codice_pratica' => 'SIM-'.now()->format('ymdHis'),
            'denominazione_agente' => $fornitore->name,
            'partita_iva_agente' => $fornitore->piva,
            'denominazione_prodotto' => $contract->getLabel(),
            'data_inserimento_pratica' => today(),
            'rata' => $profile['assignable_quota'],
            'erogato' => $quotation['max'],
            'nrate' => $profile['duration_months'],
            'amount' => $profile['requested_amount'],
            'is_notowned' => false,
            'upload_at' => now(),
            'company_id' => $fornitore->company_id,
            'pratica_stato_id' => $stato->getKey(),
            'tipoprodotto_id' => $tipo->getKey(),
            'fornitori_id' => $fornitore->getKey(),
        ]);

        $pratica->update([
            'codice_pratica' => 'SIM-'.now()->format('ymd').'-'.str_pad((string) $pratica->getKey(), 6, '0', STR_PAD_LEFT),
        ]);

        return $this->quotes->store($fornitore, $profile, $quotation, 'perfezionato', $pratica->getKey(), $user);
    }
}
