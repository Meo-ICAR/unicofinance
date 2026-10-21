<?php

namespace App\Services\Preventivazione;

use App\Models\Client;
use App\Models\Pratica;
use App\Models\PraticaLead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Unico\Core\Models\Fornitore;

/**
 * Trasmette a Mediafacile il lead della pratica (servizio di caricamento lead 1.5).
 * Il servizio risponde 200 anche in caso di errore dei dati: conta solo `Stato` che comincia con «OK».
 */
class LeadSender
{
    /** Valori ammessi per `tipologia` (tabella 3.1 della specifica) per i rapporti che non coincidono con l'etichetta. */
    private const TIPOLOGIE = [
        'privato spa' => 'Privato',
        'pensionato inpdap' => 'Pensionato altri enti',
    ];

    public function isConfigured(): bool
    {
        return filled(config('preventivatore.lead.url')) && filled(config('preventivatore.lead.passkey'));
    }

    public function alreadySent(Pratica $pratica): ?PraticaLead
    {
        return PraticaLead::where('pratica_id', $pratica->getKey())->where('is_ok', true)->latest('id')->first();
    }

    /**
     * @throws QuoteUnavailable se il servizio non è configurato o non è raggiungibile
     */
    public function send(Pratica $pratica, ?User $user = null): PraticaLead
    {
        if (! $this->isConfigured()) {
            throw new QuoteUnavailable('Servizio di caricamento lead non configurato.');
        }

        try {
            $response = Http::timeout((int) config('preventivatore.timeout', 15))
                ->withQueryParameters(['Passkey' => (string) config('preventivatore.lead.passkey')] + $this->parameters($pratica))
                ->get((string) config('preventivatore.lead.url'));
        } catch (ConnectionException $e) {
            throw new QuoteUnavailable('Servizio di caricamento lead non raggiungibile.', 0, $e);
        }

        if ($response->failed()) {
            throw new QuoteUnavailable("Il servizio di caricamento lead ha risposto {$response->status()}.");
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_use_internal_errors($previous);

        $stato = $xml ? trim((string) ($xml->xpath('//Stato')[0] ?? '')) : '';

        return PraticaLead::create([
            'pratica_id' => $pratica->getKey(),
            'is_ok' => str_starts_with(strtoupper($stato), 'OK'),
            'stato' => $stato !== '' ? mb_substr($stato, 0, 255) : 'Risposta non valida',
            'iduu' => $xml ? (trim((string) ($xml->xpath('//IDUU')[0] ?? '')) ?: null) : null,
            'user_id' => $user?->getKey(),
        ]);
    }

    /**
     * I valori non ricavabili si omettono. Nessun documento va al servizio.
     *
     * @return array<string, string>
     */
    public function parameters(Pratica $pratica): array
    {
        $client = $pratica->richiedente;
        $fornitore = $pratica->fornitori_id ? Fornitore::find($pratica->fornitori_id) : null;
        $address = $client?->addresses()->first();

        return array_filter([
            'cognome' => $client?->name ?? $pratica->cognome_cliente,
            'nome' => $client?->first_name ?? $pratica->nome_cliente,
            'data_nascita' => self::birthDate($client),
            'tipologia' => self::tipologia($client),
            'importo_richiesto' => $pratica->amount !== null ? number_format((float) $pratica->amount, 2, ',', '') : null,
            'residenza_citta' => $address?->city ?? $client?->city,
            'residenza_provincia' => $address?->province,
            'cellulare' => $client?->phone,
            'email' => $client?->email,
            'fonte' => (string) config('preventivatore.lead.fonte'),
            'annotazioni' => "Pratica {$pratica->codice_pratica}",
            'codice_fiscale' => $client?->tax_code ?? $pratica->codice_fiscale,
            'codice_fiscale_agente' => $fornitore?->cf,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** La specifica vuole «MM-GG-ANNO». */
    private static function birthDate(?Client $client): ?string
    {
        return $client?->birth_date ? Carbon::parse($client->birth_date)->format('m-d-Y') : null;
    }

    private static function tipologia(?Client $client): ?string
    {
        $label = trim((string) $client?->employment_typology);

        return $label === '' ? null : (self::TIPOLOGIE[mb_strtolower($label)] ?? $label);
    }
}
