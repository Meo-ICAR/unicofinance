<?php

namespace App\Console\Commands;

use App\Models\AgentApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAgentApiClient extends Command
{
    protected $signature = 'agent-api:client {name : Nome del client, ad es. unicoagent}';

    protected $description = 'Crea token e segreto per consegnare pratiche e documenti da unicoagent';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (AgentApiClient::query()->where('name', $name)->exists()) {
            $this->error("Esiste già un client «{$name}».");

            return self::FAILURE;
        }

        $token = Str::random(40);
        $secret = Str::random(64);

        AgentApiClient::query()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'secret' => $secret,
        ]);

        $this->info("Client «{$name}» creato. Token e segreto si mostrano solo ora: vanno in unicoagent, CRM dell'istruttoria.");
        $this->line('Token: '.$token);
        $this->line('Segreto: '.$secret);

        return self::SUCCESS;
    }
}
