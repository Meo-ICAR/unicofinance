<?php

namespace App\Services\Preventivazione;

use App\Enums\EmploymentRelationship;
use App\Enums\SimulationContractType;
use Carbon\CarbonImmutable;

/**
 * Traduce il profilo raccolto nel wizard nei parametri Mediafacile e restituisce gli importi erogati.
 */
class QuotationService
{
    public function __construct(private MediafacileClient $client) {}

    /**
     * @param  array{
     *     contract_type: string,
     *     sex: string,
     *     birth_date: string,
     *     employment_relationship: string,
     *     hired_at: ?string,
     *     starts_at: string,
     *     duration_months: int,
     *     assignable_quota: ?float,
     *     requested_amount: ?float
     * }  $profile
     * @return array{min: float, max: float, amounts: list<float>}
     */
    public function quote(array $profile): array
    {
        $raw = $this->client->request($this->parameters($profile));
        $amounts = $this->plausible($profile, MediafacileClient::validAmounts($raw));

        if ($amounts === []) {
            throw new QuoteUnavailable('Nessuna offerta valida per questi dati.');
        }

        return [
            'min' => min($amounts),
            'max' => max($amounts),
            'amounts' => $amounts,
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, string>
     */
    public function parameters(array $profile): array
    {
        $contract = SimulationContractType::from((string) $profile['contract_type']);
        $relationship = EmploymentRelationship::from((string) $profile['employment_relationship']);

        $params = [
            'Data_nascita' => CarbonImmutable::parse($profile['birth_date'])->format('m-d-Y'),
            'Sesso' => (string) $profile['sex'],
            'Tipo_contratto' => $contract->productName(),
            'Tipo_rapporto' => $relationship->getLabel(),
            'Durata' => (string) $profile['duration_months'],
        ];

        if (filled($profile['hired_at'] ?? null)) {
            $params['Data_assunzione'] = CarbonImmutable::parse($profile['hired_at'])->format('m-d-Y');
        }

        // Il servizio vuole la decorrenza anche sul prestito: senza risponde che la prima data ammissibile è un'altra.
        $params['Data_decorrenza'] = CarbonImmutable::parse($profile['starts_at'])->format('m-d-Y');

        if ($contract->asksQuotaCedibile()) {
            $params['Importo_rata'] = $this->money((float) $profile['assignable_quota']);
            $params['Rinnovo'] = 'NO';
        } else {
            $params['Importo_richiesto'] = $this->money((float) $profile['requested_amount']);
        }

        return $params;
    }

    /**
     * Su una cessione l'erogato non può superare il montante (rata × durata). Alcune banche, quando la rata ha la virgola, rispondono
     * con importi 100 volte più grandi: quelli fuori scala si dividono per 100 e si tengono se tornano nel montante, altrimenti si scartano.
     *
     * @param  array<string, mixed>  $profile
     * @param  list<float>  $amounts
     * @return list<float>
     */
    private function plausible(array $profile, array $amounts): array
    {
        if (! SimulationContractType::from((string) $profile['contract_type'])->asksQuotaCedibile()) {
            return $amounts;
        }

        $gross = (float) $profile['assignable_quota'] * (int) $profile['duration_months'];
        $corrected = array_map(fn (float $amount): float => $amount <= $gross ? $amount : round($amount / 100, 2), $amounts);

        return array_values(array_filter($corrected, fn (float $amount): bool => $amount <= $gross));
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
