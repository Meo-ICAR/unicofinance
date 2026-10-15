<?php

namespace App\Services\Istruttoria;

use App\Models\Pratica;
use App\Models\Provvigione;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Document;
use Unico\Core\Models\PraticaClient;

/**
 * Cambio di offerta: la pratica viene clonata, con richiedenti, documenti e provvigioni,
 * e la copia viene portata su un'altra banca. La pratica di partenza non cambia.
 */
class CambioOfferta
{
    /** Campi che dipendono dalla banca o dall'avanzamento e non seguono la copia. */
    private const CAMPI_AZZERATI = [
        'sended_at', 'approved_at', 'erogated_at', 'rejected_at', 'erogato',
        'abi', 'abi_name', 'offer_valid_until', 'withdrawal_terms',
    ];

    /** Campi della provvigione legati a fatturazione e pagamento: la copia riparte da zero. */
    private const PROVVIGIONE_AZZERATI = [
        'data_pagamento', 'importo_effettivo', 'stato', 'status_compenso', 'data_status', 'n_fattura',
        'data_fattura', 'invoice_number', 'fattura_id', 'proforma_id', 'paided_at', 'received_at',
        'status_pagamento',
    ];

    public static function richiedeAssicurazione(Pratica $pratica): bool
    {
        $descrizione = Str::lower($pratica->tipoprodotto?->name.' '.$pratica->denominazione_prodotto);

        return Str::contains($descrizione, ['cessione', 'delega']);
    }

    public function handle(Pratica $pratica, Cliente $banca, ?string $assicurazione = null): Pratica
    {
        $nuova = DB::connection($pratica->getConnectionName())->transaction(function () use ($pratica, $banca, $assicurazione): Pratica {
            $nuova = $pratica->replicate(self::CAMPI_AZZERATI);
            $nuova->codice_pratica = $this->nuovoCodice($pratica);
            $nuova->clienti_id = $banca->getKey();
            $nuova->denominazione_banca = $banca->name;
            $nuova->assicurazione = self::richiedeAssicurazione($pratica) ? $assicurazione : $pratica->assicurazione;
            $nuova->upload_at = now();
            $nuova->save();

            $pratica->praticaClients->each(fn (PraticaClient $richiedente) => $richiedente->replicate()
                ->forceFill(['pratica_id' => $nuova->getKey()])
                ->save());

            $pratica->documents()->with('media')->get()->each(function (Document $documento) use ($nuova): void {
                $copia = $documento->replicate(['replaced_by_id'])->forceFill([
                    'documentable_type' => $nuova->getMorphClass(),
                    'documentable_id' => $nuova->getKey(),
                ]);
                $copia->save();

                $documento->media->each(fn ($allegato) => $allegato->copy($copia, $allegato->collection_name, $allegato->disk));
            });

            return $nuova;
        });

        $this->clonaProvvigioni($pratica, $nuova, $banca);

        return $nuova;
    }

    /** Le provvigioni stanno nel database di Proforma, quindi fuori dalla transazione della pratica. */
    private function clonaProvvigioni(Pratica $pratica, Pratica $nuova, Cliente $banca): void
    {
        $pratica->provvigioni()->get()->each(function (Provvigione $provvigione) use ($nuova, $banca): void {
            $copia = $provvigione->replicate(self::PROVVIGIONE_AZZERATI);
            $copia->id = (string) Str::uuid();
            $copia->id_pratica = $nuova->getKey();
            $copia->istituto_finanziario = $banca->name;
            $copia->annullato = false;

            if ($copia->pratica_id !== null) {
                $copia->pratica_id = (string) $nuova->getKey();
            }

            $copia->save();
        });
    }

    private function nuovoCodice(Pratica $pratica): string
    {
        $base = preg_replace('/-OFF\d+$/', '', $pratica->codice_pratica);
        $numero = Pratica::query()->where('codice_pratica', 'like', $base.'-OFF%')->count() + 1;

        return $base.'-OFF'.$numero;
    }
}
