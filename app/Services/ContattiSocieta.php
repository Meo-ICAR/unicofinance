<?php

namespace App\Services;

use App\Models\Client;

/**
 * Contatti di una pratica aziendale. Le comunicazioni (richieste di documenti, solleciti) vanno al referente, altrimenti
 * al legale rappresentante, altrimenti alla società. La firma con OTP spetta sempre al legale rappresentante.
 */
class ContattiSocieta
{
    /** @return array{client: Client, email: ?string, phone: ?string, fonte: string} */
    public function comunicazioni(Client $societa): array
    {
        $referente = $this->soggetto($societa, 'referente');

        if ($referente !== null && (filled($referente->email) || filled($referente->phone))) {
            return $this->contatto($referente, 'referente');
        }

        $legale = $this->legaleRappresentante($societa);

        if ($legale !== null && (filled($legale->email) || filled($legale->phone))) {
            return $this->contatto($legale, 'legale rappresentante');
        }

        return $this->contatto($societa, 'società');
    }

    /** Il firmatario (legale rappresentante) o, se manca, la società; il telefono serve all'OTP. */
    public function firma(Client $societa): ?Client
    {
        return $this->legaleRappresentante($societa);
    }

    public function legaleRappresentante(Client $societa): ?Client
    {
        return $this->soggetto($societa, 'legale_rappresentante')
            ?? ($societa->legal_representative_id ? Client::query()->find($societa->legal_representative_id) : null);
    }

    private function soggetto(Client $societa, string $ruolo): ?Client
    {
        $relazione = $societa->soci()->where('role', $ruolo)->latest('id')->first();

        return $relazione === null ? null : Client::query()->find($relazione->client_id);
    }

    /** @return array{client: Client, email: ?string, phone: ?string, fonte: string} */
    private function contatto(Client $client, string $fonte): array
    {
        return ['client' => $client, 'email' => $client->email, 'phone' => $client->phone, 'fonte' => $fonte];
    }
}
