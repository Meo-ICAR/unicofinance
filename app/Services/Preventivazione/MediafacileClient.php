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
        $account = [
            'Provvigione' => self::decimal((string) config('preventivatore.provvigione')),
            'ID_listino' => (string) config('preventivatore.id_listino'),
            'ID_agente' => (string) config('preventivatore.id_agente'),
        ];

        if ($url === '' || $passkey === '' || in_array('', $account, true)) {
            throw new QuoteUnavailable('Preventivatore non configurato.');
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('preventivatore.timeout', 15))
                ->post($url, ['passkey' => $passkey] + $account + $params);
        } catch (ConnectionException $e) {
            throw new QuoteUnavailable('Servizio di preventivazione non raggiungibile.', 0, $e);
        }

        if ($response->failed()) {
            throw new QuoteUnavailable("Il servizio di preventivazione ha risposto {$response->status()}.");
        }

        return $response->body();
    }

    /**
     * @return list<float> importi erogati delle offerte valide (Errore = 2) dal fmresultset FileMaker
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

        $xml->registerXPathNamespace('fm', 'http://www.filemaker.com/xml/fmresultset');

        foreach ($xml->xpath('//fm:record') ?: [] as $record) {
            $fields = [];

            foreach ($record->children('http://www.filemaker.com/xml/fmresultset')->field ?? [] as $field) {
                $fields[(string) $field->attributes()['name']] = trim((string) $field->children('http://www.filemaker.com/xml/fmresultset')->data);
            }

            if (($fields['Errore'] ?? '') !== '2') {
                continue;
            }

            $amounts[] = self::number($fields['Importo_erogato'] ?? '0');
        }

        return $amounts;
    }

    /** Il servizio vuole i valori numerici con due decimali e la virgola («9» diventa «9,00»); un valore vuoto resta vuoto. */
    private static function decimal(string $value): string
    {
        return trim($value) === '' ? '' : number_format(self::number($value), 2, ',', '');
    }

    private static function number(string $value): float
    {
        $value = trim($value);

        return (float) (str_contains($value, ',') ? str_replace(',', '.', str_replace('.', '', $value)) : $value);
    }
}
