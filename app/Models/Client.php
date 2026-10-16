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
    protected static function booted(): void
    {
        static::saved(function (self $client): void {
            if ($client->wasChanged('employer_id') || ($client->wasRecentlyCreated && $client->employer_id !== null)) {
                $client->allineaRelazioneDatore();
            }
        });
    }

    /** Il datore di lavoro indicato sul cliente è legato a lui con il ruolo `dipendente` (e solo a quello attuale). */
    public function allineaRelazioneDatore(): void
    {
        ClientRelation::query()
            ->where('client_id', $this->getKey())
            ->where('role', ClientRelation::RUOLO_DIPENDENTE)
            ->when($this->employer_id !== null, fn ($query) => $query->where('company_id', '!=', $this->employer_id))
            ->delete();

        if ($this->employer_id !== null) {
            ClientRelation::query()->firstOrCreate(['company_id' => $this->employer_id, 'client_id' => $this->getKey(), 'role' => ClientRelation::RUOLO_DIPENDENTE]);
        }
    }

    /** Persona giuridica con almeno un dipendente (cliente con ruolo `dipendente`): è un datore di lavoro, non un'azienda cliente. */
    public function isDatoreDiLavoro(): bool
    {
        return $this->exists && ! $this->is_person && ($this->soci()->where('role', ClientRelation::RUOLO_DIPENDENTE)->exists() || $this->employees()->exists());
    }

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
            ->orderByRaw('data_inserimento_pratica IS NULL')
            ->orderByDesc('data_inserimento_pratica')
            ->orderByDesc('id');
    }

    /** Soggetti collegati a una persona giuridica: soci, amministratori, titolari effettivi. */
    public function soci(): HasMany
    {
        return $this->hasMany(ClientRelation::class, 'company_id');
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class, 'codice_fiscale', 'tax_code');
    }
}
