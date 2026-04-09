<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestRegistry extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'request_date' => 'date',
        'response_deadline' => 'date',
        'response_date' => 'date',
        'sla_breach' => 'boolean',
        'extension_granted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $registry) {
            $registry->request_number = self::generateRequestNumber();

            if (!$registry->response_deadline) {
                $registry->response_deadline = $registry->request_date->copy()->addDays(30);
            }
        });

        static::saving(function (self $registry) {
            // Marca automaticamente come SLA breach se la risposta è arrivata dopo la scadenza
            if ($registry->response_date && $registry->response_date->gt($registry->response_deadline)) {
                $registry->sla_breach = true;
            }

            // Se lo stato è 'evasa', 'respinta' o 'parzialmente_evasa' e non c'è una data risposta, impostala
            if (
                in_array($registry->status, ['evasa', 'respinta', 'parzialmente_evasa'])
                && !$registry->response_date
            ) {
                $registry->response_date = now()->toDateString();
            }
        });
    }

    /**
     * Genera un numero progressivo univoco: REQ-YYYY-NNNNN
     */
    public static function generateRequestNumber(): string
    {
        $year = now()->year;
        $last = self::whereYear('request_date', $year)
            ->withTrashed()
            ->orderBy('id', 'desc')
            ->first();

        $progressive = $last ? (int) substr($last->request_number, -5) + 1 : 1;

        return sprintf('REQ-%d-%05d', $year, $progressive);
    }

    // ── Relationships ──────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function dataSubject(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestRegistryAttachment::class, 'registry_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RequestRegistryAction::class, 'registry_id')
            ->orderBy('action_date', 'desc');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(RequestRegistryProcess::class, 'registry_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->whereIn('status', ['ricevuta', 'in_lavorazione']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('response_deadline', '<', now())
            ->whereNotIn('status', ['evasa', 'respinta']);
    }

    public function scopeForOversightBody($query)
    {
        return $query->where('requester_type', 'organismo_vigilanza');
    }

    public function scopeBreach($query)
    {
        return $query->where('sla_breach', true);
    }

    public function scopeByRequestType($query, string $type)
    {
        return $query->where('request_type', $type);
    }
}
