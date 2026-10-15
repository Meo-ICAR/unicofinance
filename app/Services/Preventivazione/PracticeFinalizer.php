<?php

namespace App\Services\Preventivazione;

use App\Enums\SimulationContractType;
use App\Models\FinancingQuote;
use App\Models\User;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

/** Apre la pratica del preventivo e, dopo, la porta in stato PERFEZIONARE. */
class PracticeFinalizer
{
    public function __construct(private FinancingQuoteRecorder $quotes) {}

    /**
     * @param  array<string, mixed>  $profile
     * @param  array{min: ?float, max: ?float}  $quotation
     */
    public function saveQuote(Fornitore $fornitore, array $profile, array $quotation, User $user): FinancingQuote
    {
        $pratica = $this->createPratica($fornitore, $profile, $quotation, 'PREVENTIVO');

        return $this->quotes->store($fornitore, $profile, $quotation, 'salvato', $pratica->getKey(), $user);
    }

    public function perfect(FinancingQuote $quote): FinancingQuote
    {
        $pratica = $quote->pratica;

        if ($pratica === null) {
            throw new QuoteUnavailable('Il preventivo non ha una pratica da perfezionare.');
        }

        $productName = SimulationContractType::from($quote->contract_type)->productName();
        $pratica->update([
            'pratica_stato_id' => $this->status($productName, 'PERFEZIONARE')->getKey(),
        ]);
        app(PracticeTaskDocuments::class)->attachToPractice($pratica);
        $quote->update(['status' => 'perfezionato']);

        return $quote->fresh(['pratica.praticaStato', 'pratica.tipoprodotto']);
    }

    /** Porta la pratica a un altro stato del suo prodotto (es. FASCICOLO COMPLETO: modulistica da firmare). */
    public function advance(Pratica $pratica, string $productName, string $statusCode): void
    {
        $pratica->update(['pratica_stato_id' => $this->status($productName, $statusCode)->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array{min: ?float, max: ?float}  $quotation
     */
    private function createPratica(Fornitore $fornitore, array $profile, array $quotation, string $statusCode): Pratica
    {
        $contract = SimulationContractType::from((string) $profile['contract_type']);
        $productName = $contract->productName();
        $tipo = TipoProdotto::query()->where('name', $productName)->first();

        if ($tipo === null) {
            throw new QuoteUnavailable("Non trovo il tipo prodotto {$productName}.");
        }

        $pratica = Pratica::query()->create([
            'codice_pratica' => 'SIM-'.now()->format('ymdHis'),
            'denominazione_agente' => $fornitore->name,
            'partita_iva_agente' => $fornitore->piva,
            'denominazione_prodotto' => $contract->getLabel(),
            // Prodotti per società: ragione sociale e partita IVA identificano il cliente prima che esista l'anagrafica.
            'cognome_cliente' => $profile['company_name'] ?? null,
            'codice_fiscale' => $profile['vat_number'] ?? null,
            'data_inserimento_pratica' => today(),
            'rata' => $profile['assignable_quota'] ?? null,
            'erogato' => $quotation['max'],
            'nrate' => $profile['duration_months'],
            'amount' => $profile['requested_amount'] ?? null,
            'is_notowned' => false,
            'upload_at' => now(),
            'company_id' => $fornitore->company_id,
            'pratica_stato_id' => $this->status($productName, $statusCode)->getKey(),
            'tipoprodotto_id' => $tipo->getKey(),
            'fornitori_id' => $fornitore->getKey(),
        ]);

        $pratica->update([
            'codice_pratica' => 'SIM-'.now()->format('ymd').'-'.str_pad((string) $pratica->getKey(), 6, '0', STR_PAD_LEFT),
        ]);

        return $pratica;
    }

    public function status(string $productName, string $code): PraticaStato
    {
        return PraticaStato::query()->firstOrCreate(
            ['codice' => $code, 'tipo_finanziamento' => $productName],
            [
                'name' => $code,
                'ordine' => 0,
                'is_rejected' => false,
                'is_working' => true,
                'is_estingued' => false,
                'colore' => 'gray',
            ],
        );
    }
}
