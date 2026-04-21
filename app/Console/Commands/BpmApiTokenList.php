<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

/**
 * php artisan bpm:api-token:list          — list all tokens
 * php artisan bpm:api-token:list --revoke=5  — revoke token with ID 5
 */
class BpmApiTokenList extends Command
{
    protected $signature = 'bpm:api-token:list
                            {--company= : Filter by company ID}
                            {--revoke=  : Revoke a token by its ID}';

    protected $description = 'List or revoke cross-app BPM API tokens';

    public function handle(): int
    {
        // ── Revoke ─────────────────────────────────────────────────────────────
        if ($revokeId = $this->option('revoke')) {
            $token = ApiToken::find((int) $revokeId);

            if (! $token) {
                $this->error("Token [{$revokeId}] not found.");
                return self::FAILURE;
            }

            $token->delete();
            $this->info("Token [{$revokeId}] ({$token->name}) revoked successfully.");
            return self::SUCCESS;
        }

        // ── List ───────────────────────────────────────────────────────────────
        $query = ApiToken::with('company')->withTrashed();

        if ($companyId = $this->option('company')) {
            $query->where('company_id', (int) $companyId);
        }

        $tokens = $query->orderBy('id')->get();

        if ($tokens->isEmpty()) {
            $this->info('No API tokens found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Company', 'Name', 'Caller App', 'Abilities', 'Expires At', 'Last Used', 'Status'],
            $tokens->map(fn ($t) => [
                $t->id,
                optional($t->company)->name ?? "company_{$t->company_id}",
                $t->name,
                $t->caller_app ?? '—',
                implode(', ', $t->abilities ?? ['*']),
                $t->expires_at?->toDateString() ?? 'Never',
                $t->last_used_at?->diffForHumans() ?? 'Never',
                $t->deleted_at ? '⛔ revoked' : ($t->expires_at && $t->expires_at->isPast() ? '⏰ expired' : '✅ active'),
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
