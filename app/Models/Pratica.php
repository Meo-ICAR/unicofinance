<?php

namespace App\Models;

use App\Services\Agenti\AgentRequestDetails;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Unico\Core\Models\ActivityLog;
use Unico\Core\Models\Address;
use Unico\Core\Models\Branch;
use Unico\Core\Models\Document;
use Unico\Core\Models\Pratica as CorePratica;
use Unico\Core\Models\Website;

class Pratica extends CorePratica
{
    public function getMorphClass(): string
    {
        return 'pratica';
    }

    /**
     * Pratiche in corso: lo stato è in lavorazione e non è né approvato, né respinto, né sospeso.
     *
     * @param  Builder<Pratica>  $query
     */
    #[Scope]
    protected function inCorso(Builder $query): void
    {
        $query->whereHas('praticaStato', fn (Builder $stato) => $stato
            ->where('is_working', true)
            ->where('is_approved', false)
            ->where('is_rejected', false)
            ->where('is_suspended', false));
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function websites(): MorphMany
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    public function branches(): MorphMany
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function provvigioni(): HasMany
    {
        return $this->hasMany(Provvigione::class, 'id_pratica', 'codice_pratica');
    }

    /** Richiedente sull'anagrafica core, collegato dal codice fiscale. */
    public function richiedente(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'codice_fiscale', 'tax_code');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /** Richiesta, aggiornamenti e note arrivati da unicoagent (registro delle attività del core). */
    public function attivitaAgente(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->where('log_name', AgentRequestDetails::LOG);
    }

    /** Altre pratiche dello stesso richiedente. */
    public function praticheRichiedente(): HasMany
    {
        $relation = $this->hasMany(self::class, 'codice_fiscale', 'codice_fiscale');

        if (blank($this->codice_fiscale)) {
            $relation->whereRaw('0 = 1');
        }

        return $relation->whereKeyNot($this->getKey());
    }

    /** Impegni di terzi (finanziamenti in corso altrove) dello stesso codice fiscale o partita IVA. */
    public function impegniTerzi(): HasMany
    {
        return $this->praticheRichiedente()->where('is_notowned', true);
    }

    /** La pratica che estingue questo impegno di terzi (`estingued_by`). */
    public function estintaDa(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estingued_by');
    }

    /** Gli impegni di terzi che questa pratica estingue. */
    public function impegniEstinti(): HasMany
    {
        return $this->hasMany(self::class, 'estingued_by');
    }
}
