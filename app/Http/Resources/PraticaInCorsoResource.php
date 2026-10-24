<?php

namespace App\Http\Resources;

use App\Models\Pratica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una riga della tabella delle pratiche in corso per unicobpm.
 *
 * @mixin Pratica
 */
class PraticaInCorsoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'codice_pratica' => $this->codice_pratica,
            'cliente' => trim($this->cognome_cliente.' '.$this->nome_cliente),
            'prodotto' => $this->tipoprodotto?->name,
            'banca' => $this->denominazione_banca ?? $this->clienti?->name,
            'agente' => $this->denominazione_agente ?? $this->fornitori?->name,
            'employee_id' => $this->employee_id,
            'istruttore' => $this->employee?->name,
            'stato_pratica' => $this->praticaStato?->name,
            'data_inserimento_pratica' => $this->data_inserimento_pratica?->toDateString(),
        ];
    }
}
