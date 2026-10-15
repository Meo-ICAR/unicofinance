<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Unico\Core\Models\Address;
use Unico\Core\Models\Client as CoreClient;

/** Richiedente della pratica: anagrafica core, con indirizzi, KYC e AML. */
class Client extends CoreClient
{
    public function getMorphClass(): string
    {
        return 'client';
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function kycQuestionnaires(): HasMany
    {
        return $this->hasMany(KycQuestionnaire::class, 'client_id');
    }

    public function questionarioKyc(): HasOne
    {
        return $this->hasOne(KycQuestionnaire::class, 'client_id')->latestOfMany();
    }

    /** Ultima pratica inserita dal cliente: ne determina i dati pertinenti sulla scheda. */
    public function ultimaPratica(): HasOne
    {
        return $this->hasOne(Pratica::class, 'codice_fiscale', 'tax_code')
            ->ofMany(['data_inserimento_pratica' => 'max', 'id' => 'max']);
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class, 'codice_fiscale', 'tax_code');
    }
}
