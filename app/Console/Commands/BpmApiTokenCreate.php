<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * php artisan bpm:api-token:create
 *
 * Interactive wizard that generates a new ApiToken for a company.
 * The plain-text token is printed ONCE — copy it into the calling app's .env.
 */
class BpmApiTokenCreate extends Command
{
    protected $signature = 'bpm:api-token:create
                            {--company=  : Company ID (skips interactive prompt)}
                            {--name=     : Token name label}
                            {--caller=   : Caller app identifier, e.g. mediazione_app}
                            {--expires=  : Expiry date in YYYY-MM-DD format (optional)}';

    protected $description = 'Generate a new API token for cross-app BPM process execution';

    public function handle(): int
    {
        $this->info('── BPM Cross-App API Token Generator ──────────────────');

        // ── Company ────────────────────────────────────────────────────────────
        $companyId = $this->option('company')
            ?? $this->ask('Company ID (tenant that owns the token)');

        $company = Company::find((int) $companyId);

        if (! $company) {
            $this->error("Company [{$companyId}] not found.");
            return self::FAILURE;
        }

        // ── Metadata ───────────────────────────────────────────────────────────
        $name = $this->option('name')
            ?? $this->ask('Token name (human label for auditing)', 'Cross-app BPM caller');

        $callerApp = $this->option('caller')
            ?? $this->ask('Caller app identifier (e.g. mediazione_app)', null);

        $expiresInput = $this->option('expires')
            ?? $this->ask('Expiry date YYYY-MM-DD (blank = no expiry)', null);

        $expiresAt = $expiresInput
            ? Carbon::parse($expiresInput)->endOfDay()
            : null;

        // ── Create ─────────────────────────────────────────────────────────────
        ['token' => $apiToken, 'plain' => $plain] = ApiToken::createWithPlainToken([
            'company_id'  => $company->id,
            'name'        => $name,
            'caller_app'  => $callerApp,
            'abilities'   => ['bpm:create_execution'],
            'expires_at'  => $expiresAt,
        ]);

        // ── Output ─────────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('  <fg=green>✓ Token created successfully.</>');
        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Token ID',    $apiToken->id],
                ['Company',     "{$company->name} (ID: {$company->id})"],
                ['Name',        $apiToken->name],
                ['Caller App',  $apiToken->caller_app ?? '—'],
                ['Abilities',   implode(', ', $apiToken->abilities ?? ['*'])],
                ['Expires At',  $expiresAt ? $expiresAt->toDateTimeString() : 'Never'],
            ]
        );

        $this->newLine();
        $this->warn('  ⚠  Copy this token NOW. It will not be shown again.');
        $this->newLine();
        $this->line("  <fg=yellow;options=bold>UNICOFINANCE_BPM_TOKEN={$plain}</>");
        $this->newLine();
        $this->line('  Add it to the calling app\'s .env:');
        $this->line("  <fg=cyan>UNICOFINANCE_BPM_TOKEN={$plain}</>");
        $this->line("  <fg=cyan>UNICOFINANCE_BPM_URL=" . config('app.url') . "</>");
        $this->newLine();
        $this->line('  Then call:');
        $this->line('  <fg=cyan>POST {UNICOFINANCE_BPM_URL}/api/bpm/executions</>');
        $this->line('  <fg=cyan>Authorization: Bearer {UNICOFINANCE_BPM_TOKEN}</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
