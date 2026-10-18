<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateAgentApiClient extends Command
{
    protected $signature = 'agent-api:client {name : Nome dell\'app (es. unicoagent)} {--rotate : Rigenera token e segreto di un\'app esistente}';

    protected $description = 'Crea (o rigenera) le credenziali di un\'app che usa le API in ingresso. Token e segreto si mostrano una sola volta.';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! $this->option('rotate') && ApiClient::where('name', $name)->exists()) {
            $this->error("L'app «{$name}» esiste già: usa --rotate per rigenerare le credenziali.");

            return self::FAILURE;
        }

        // Le vecchie credenziali spariscono solo se le nuove sono salvate.
        $issued = DB::connection((new ApiClient)->getConnectionName())->transaction(function () use ($name): array {
            ApiClient::where('name', $name)->lockForUpdate()->first()?->delete();

            return ApiClient::issue($name);
        });

        $this->info("Credenziali di «{$name}» (non verranno più mostrate):");
        $this->line('Token  : '.$issued['token']);
        $this->line('Segreto: '.$issued['secret']);

        return self::SUCCESS;
    }
}
