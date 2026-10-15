<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Soci, amministratori e titolari effettivi di una persona giuridica. La tabella è di unicofinance, i clienti sono nel core. */
class ClientRelation extends Model
{
    protected $table = 'client_relations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'shares_percentage' => 'decimal:2',
        ];
    }

    /** Senza questo il legame erediterebbe la connessione core del cliente da cui parte la relazione. */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'company_id');
    }
}
