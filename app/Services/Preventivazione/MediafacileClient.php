<?php

namespace App\Services\Preventivazione;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Chiamata al servizio di preventivazione Mediafacile e lettura degli importi erogati.
 */
class MediafacileClient
{
    /**
     * @param  array<string, string>  $params
     */
    public function request(array $params): string
    {
        $url = (string) config('preventivatore.url');
        $passkey = (string) config('preventivatore.passkey');

        if ($url === '' || $passkey === '') {
            throw new QuoteUnavailable('Preventivatore non configurato.');
        }

        try {
            $response = Http::timeout((int) config('preventivatore.timeout', 15))
                ->withQueryParameters(['Passkey' => $passkey] + $params)
                ->post($url);
        } catch (ConnectionException $e) {
            throw new QuoteUnavailable('Servizio di preventivazione non raggiungibile.', 0, $e);
        }

        if ($response->failed()) {
            throw new QuoteUnavailable("Il servizio di preventivazione ha risposto {$response->status()}.");
        }

        return $response->body();
    }

    /**
     * @return list<float> importi erogati delle offerte valide (Errore = 2)
     */
    public static function validAmounts(string $raw): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new QuoteUnavailable('Risposta del servizio di preventivazione non valida.');
        }

        $amounts = [];

        foreach ($xml->xpath('//Importo_erogato/..') ?: [] as $node) {
            if (trim((string) $node->Errore) !== '2') {
                continue;
            }

            $amounts[] = self::number((string) $node->Importo_erogato);
        }

        return $amounts;
    }

    private static function number(string $value): float
    {
        $value = trim($value);

        return (float) (str_contains($value, ',') ? str_replace(',', '.', str_replace('.', '', $value)) : $value);
    }
}
