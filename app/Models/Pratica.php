<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        return $this->hasMany(Provvigione::class, 'id_pratica', 'id');
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
}
