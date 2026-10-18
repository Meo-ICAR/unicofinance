<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;

class CreateAgentApiClient extends Command
{
    protected $signature = 'agent-api:client {name : Nome dell\'app (es. unicoagent)} {--rotate : Rigenera token e segreto di un\'app esistente}';

    protected $description = 'Crea (o rigenera) le credenziali di un\'app che usa le API in ingresso. Token e segreto si mostrano una sola volta.';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $existing = ApiClient::where('name', $name)->first();

        if ($existing && ! $this->option('rotate')) {
            $this->error("L'app «{$name}» esiste già: usa --rotate per rigenerare le credenziali.");

            return self::FAILURE;
        }

        $existing?->delete();
        $issued = ApiClient::issue($name);

        $this->info("Credenziali di «{$name}» (non verranno più mostrate):");
        $this->line('Token  : '.$issued['token']);
        $this->line('Segreto: '.$issued['secret']);

        return self::SUCCESS;
    }
}
