<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * ApiToken — machine-to-machine auth for cross-app BPM triggers.
 *
 * External Laravel 13 apps (on a different DB, same server) use a plain-text
 * token in the Authorization: Bearer header.  This model stores the SHA-256
 * hash.  The plain-text token is only returned once, during creation.
 */
class ApiToken extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $fillable = [
        'company_id',
        'name',
        'token',
        'caller_app',
        'abilities',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'abilities'    => 'array',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Factory helpers ────────────────────────────────────────────────────────

    /**
     * Generate a new plain-text token, store its SHA-256 hash, and return
     * an array with both the model and the once-visible plain text.
     *
     * @param  array{company_id: int, name: string, caller_app?: string, abilities?: array, expires_at?: \Carbon\Carbon|null}  $attributes
     * @return array{token: self, plain: string}
     */
    public static function createWithPlainToken(array $attributes): array
    {
        $plain = Str::random(60);

        $model = self::create(array_merge($attributes, [
            'token' => hash('sha256', $plain),
        ]));

        return ['token' => $model, 'plain' => $plain];
    }

    // ── Query helpers ──────────────────────────────────────────────────────────

    /**
     * Find an active token record by its plain-text value.
     */
    public static function findByPlain(string $plain): ?self
    {
        return self::where('token', hash('sha256', $plain))
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    // ── Ability checks ─────────────────────────────────────────────────────────

    public function can(string $ability): bool
    {
        if (empty($this->abilities)) {
            return true; // wildcard when no restrictions set
        }

        return in_array('*', $this->abilities, true)
            || in_array($ability, $this->abilities, true);
    }
}
