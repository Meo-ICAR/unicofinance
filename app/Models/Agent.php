<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Agent — represents a candidate / agent in the "Reclutamento e Onboarding Rete Agenziale" BPM process.
 *
 * Lifecycle:
 *   lead → in_valutazione → approvato → contrattualizzato → attivo
 *                                        ↳ rifiutato (terminal)
 */
class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Possible status values for the agent lifecycle.
     */
    public const STATUS_LEAD = 'lead';
    public const STATUS_IN_VALUTAZIONE = 'in_valutazione';
    public const STATUS_APPROVATO = 'approvato';
    public const STATUS_RIFIUTATO = 'rifiutato';
    public const STATUS_CONTRATTUALIZZATO = 'contrattualizzato';
    public const STATUS_ATTIVO = 'attivo';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_LEAD,
        self::STATUS_IN_VALUTAZIONE,
        self::STATUS_APPROVATO,
        self::STATUS_RIFIUTATO,
        self::STATUS_CONTRATTUALIZZATO,
        self::STATUS_ATTIVO,
    ];

    protected $guarded = ['id'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'fiscal_code',
        'email_personal',
        'email_corporate',
        'phone',
        'oam_number',
        'oam_at',
        'user_id',
        'status',
        'contract_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_personal' => 'encrypted',
            'email_corporate' => 'encrypted',
            'phone' => 'encrypted',
            'fiscal_code' => 'encrypted',
            'oam_number' => 'string',
        ];
    }

    /* ─── Relationships ─── */

    /**
     * Task executions targeting this agent (polymorphic).
     */
    public function taskExecutions(): MorphMany
    {
        return $this->morphMany(TaskExecution::class, 'target');
    }

    /**
     * The User account created for this agent (if provisioned).
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ─── Helpers ─── */

    /**
     * Full name of the agent.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Whether the agent has a corporate email provisioned.
     */
    public function hasCorporateEmail(): bool
    {
        return filled($this->email_corporate);
    }

    /**
     * Whether the agent has a generated contract.
     */
    public function hasContract(): bool
    {
        return filled($this->contract_path);
    }

    /**
     * Whether the agent is in the active state.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ATTIVO;
    }
}
