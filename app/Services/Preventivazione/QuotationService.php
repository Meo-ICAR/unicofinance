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
        $amounts = MediafacileClient::validAmounts($raw);

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

        if ($contract->asksQuotaCedibile()) {
            $params['Data_decorrenza'] = CarbonImmutable::parse($profile['starts_at'])->format('m-d-Y');
            $params['Importo_rata'] = $this->money((float) $profile['assignable_quota']);
            $params['Rinnovo'] = 'NO';
        } else {
            $params['Importo_richiesto'] = $this->money((float) $profile['requested_amount']);
        }

        return $params;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
