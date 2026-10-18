<?php

namespace App\Services\Agenti;

use App\Models\Client;
use App\Models\Pratica;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Unico\Core\Models\ActivityLog;

/**
 * Dati della richiesta oltre ai minimi (nascita, residenza, consensi, durata) e registro di ciò che arriva da unicoagent.
 * Alla prima consegna si scrivono solo i campi vuoti dell'anagrafica, così non si perde quello che c'era già; un aggiornamento
 * dell'agente (pratica ancora da perfezionare) li sostituisce. Il registro sta in `activity_log` del core (log `unicoagent`).
 */
class AgentRequestDetails
{
    public const LOG = 'unicoagent';

    /** Indirizzo di residenza delle persone fisiche, come nella simulazione. */
    private const RESIDENZA = 1;

    /**
     * @param  array<string,mixed>  $cliente
     * @return list<string> campi scritti
     */
    public function fillClient(Client $client, array $cliente, bool $overwrite): array
    {
        $values = array_filter([
            'name' => $this->text($cliente['cognome'] ?? null),
            'first_name' => $client->is_company ? null : $this->text($cliente['nome'] ?? null),
            'email' => filled($cliente['email'] ?? null) ? Str::lower(trim($cliente['email'])) : null,
            'phone' => $this->text($cliente['telefono'] ?? null),
            'birth_date' => $this->text($cliente['data_nascita'] ?? null),
            'birth_place' => $this->text($cliente['luogo_nascita'] ?? null),
        ], filled(...));

        $changed = $this->assign($client, $values, $overwrite);
        $client->save();

        if (! $client->is_company && is_array($cliente['residenza'] ?? null) && $this->residence($client, $cliente['residenza'], $overwrite)) {
            $changed[] = 'residenza';
        }

        return $changed;
    }

    /**
     * Privacy: ricevuta = informativa letta, verificata = consenso generale. Con `$revocable` un `privacy_verificata_il` nullo
     * inviato esplicitamente toglie il consenso (l'agente ha rifiutato l'informativa).
     *
     * @param  array<string,mixed>  $consenso
     * @return list<string> campi scritti
     */
    public function applyConsent(Client $client, array $consenso, bool $revocable): array
    {
        $changed = [];

        if (filled($consenso['privacy_ricevuta_il'] ?? null) && ($revocable || $client->privacy_policy_read_at === null)) {
            $client->privacy_policy_read_at = Carbon::parse($consenso['privacy_ricevuta_il']);
            $changed[] = 'privacy_policy_read_at';
        }

        if (filled($consenso['privacy_verificata_il'] ?? null)) {
            if ($revocable || $client->general_consent_at === null) {
                $client->general_consent_at = Carbon::parse($consenso['privacy_verificata_il']);
                $client->privacy_consent = true;
                $changed[] = 'general_consent_at';
            }
        } elseif ($revocable && array_key_exists('privacy_verificata_il', $consenso) && ($client->general_consent_at !== null || $client->privacy_consent)) {
            $client->general_consent_at = null;
            $client->privacy_consent = false;
            $changed[] = 'general_consent_at';
        }

        $client->save();

        return $changed;
    }

    /**
     * @param  array<string,mixed>  $data  sezione «pratica» della richiesta
     * @return list<string> campi scritti
     */
    public function fillPratica(Pratica $pratica, array $data, bool $overwrite): array
    {
        $changed = $this->assign($pratica, array_filter([
            'amount' => $data['importo_richiesto'] ?? null,
            'nrate' => $data['durata_mesi'] ?? null,
        ], fn ($value): bool => $value !== null), $overwrite);

        $pratica->save();

        return $changed;
    }

    /**
     * Una voce del registro della pratica: la richiesta, un aggiornamento o una nota. Nessun dato personale nelle proprietà
     * oltre al testo che l'agente ha scritto per l'istruttore.
     *
     * @param  array<string,mixed>  $properties
     */
    public function log(Pratica $pratica, string $event, string $description, array $properties = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'log_name' => self::LOG,
            'event' => $event,
            'description' => Str::limit($description, 5000, ''),
            'subject_type' => $pratica->getMorphClass(),
            'subject_id' => $pratica->getKey(),
            'properties' => array_filter($properties, fn ($value): bool => $value !== null && $value !== []),
            'request_id' => request()?->header('X-Correlation-Id'),
        ]);
    }

    public function findNote(Pratica $pratica, string $originId): ?ActivityLog
    {
        return ActivityLog::query()
            ->where('log_name', self::LOG)->where('event', 'nota')
            ->where('subject_type', $pratica->getMorphClass())->where('subject_id', $pratica->getKey())
            ->where('properties->id_origine', $originId)
            ->first();
    }

    /**
     * Ciò che della richiesta non ha un campo in anagrafica: risposte del questionario, fascia d'importo, agente, contatto diretto,
     * documenti attesi. Resta nel registro perché l'istruttore lo veda.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function requestSummary(array $data): array
    {
        return [
            'prodotto' => $data['prodotto_etichetta'] ?? $data['prodotto'] ?? null,
            'risposte' => $data['risposte'] ?? null,
            'importo_fascia' => Arr::get($data, 'pratica.importo_fascia'),
            'durata_mesi' => Arr::get($data, 'pratica.durata_mesi'),
            'agente' => array_filter(Arr::only((array) ($data['agente'] ?? []), ['nome', 'sigla', 'whatsapp'])),
            'contatto_diretto' => Arr::get($data, 'consenso.contatto_diretto'),
            'documenti' => collect($data['documenti'] ?? [])->map(fn (array $d): array => array_filter(Arr::only($d, ['codice', 'nome', 'obbligo', 'stato'])))->values()->all(),
        ];
    }

    /**
     * @param  array<string,mixed>  $values
     * @return list<string>
     */
    private function assign(Client|Pratica $model, array $values, bool $overwrite): array
    {
        $changed = [];

        foreach ($values as $field => $value) {
            if (($overwrite || blank($model->getAttribute($field))) && (string) $model->getRawOriginal($field) !== (string) $value) {
                $model->setAttribute($field, $value);
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /** @param  array<string,mixed>  $residenza */
    private function residence(Client $client, array $residenza, bool $overwrite): bool
    {
        $values = array_filter([
            'street' => $this->text($residenza['indirizzo'] ?? null),
            'city' => $this->text($residenza['citta'] ?? null),
            'province' => filled($residenza['provincia'] ?? null) ? Str::upper(trim($residenza['provincia'])) : null,
        ], filled(...));

        if ($values === []) {
            return false;
        }

        $existing = $client->addresses()->where('address_type_id', self::RESIDENZA)->first();

        // Senza sovrascrittura si completano solo i campi vuoti della residenza che c'è già.
        if ($existing !== null && ! $overwrite) {
            $values = array_filter($values, fn (string $value, string $field): bool => blank($existing->getAttribute($field)), ARRAY_FILTER_USE_BOTH);
        }

        if ($values === []) {
            return false;
        }

        $client->addresses()->updateOrCreate(['address_type_id' => self::RESIDENZA], ['name' => 'Residenza', ...$values]);

        return true;
    }

    private function text(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
