<?php

namespace App\Services;

use App\Enums\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenApiCompanyService
{
    public function __construct(private readonly ApiCallLogger $logger) {}

    /**
     * Dati societari (IT-advanced) e soci con quota >= 10% (IT-shareholders se mancano nella prima risposta).
     *
     * @return array{company: array{name: ?string, vat: ?string, tax_code: ?string, pec: ?string, ateco: ?string, cciaa: ?string, activity_status: ?string, address: ?string, legal_form: ?string, sdi_code: ?string, started_at: ?string, balance: array<string, int|float|null>, office: array<string, ?string>}, shareholders: array<int, array{is_company: bool, name: ?string, first_name: ?string, tax_code: ?string, percent: ?float}>}
     *
     * @throws RuntimeException
     */
    public function fetchCompany(string $vat, ?Model $subject = null): array
    {
        $vat = preg_replace('/^IT/i', '', trim($vat));
        $advanced = $this->get('IT-advanced', $vat, 'company_advanced', (float) config('services.openapi.cost_advanced'), $subject);
        $data = $this->firstItem($advanced);

        if ($data === []) {
            throw new RuntimeException('Nessuna azienda trovata per la partita IVA indicata.');
        }

        $shareholders = (array) ($data['shareHolders'] ?? $data['shareholders'] ?? []);

        if ($shareholders === []) {
            $shareholders = (array) $this->get('IT-shareholders', $vat, 'shareholders', (float) config('services.openapi.cost_shareholders'), $subject)['data'];
        }

        $office = (array) ($data['address']['registeredOffice'] ?? []);
        $ateco = $data['atecoClassification']['ateco'] ?? null;
        $atecoCode = is_array($ateco) ? ($ateco['code'] ?? null) : $ateco;

        return [
            'company' => [
                'name' => $data['companyName'] ?? null,
                'vat' => isset($data['vatCode']) ? (string) $data['vatCode'] : null,
                'tax_code' => isset($data['taxCode']) ? (string) $data['taxCode'] : null,
                'pec' => $data['pec'] ?? null,
                'ateco' => $this->formatAteco($atecoCode),
                'legal_form' => $data['detailedLegalForm']['description'] ?? null,
                'started_at' => $data['startDate'] ?? null,
                'balance' => $this->lastBalance($data),
                'sdi_code' => $data['sdiCode'] ?? null,
                'office' => [
                    'address' => trim(($office['toponym'] ?? '').' '.($office['street'] ?? '')) ?: ($office['streetName'] ?? null),
                    'street_number' => $office['streetNumber'] ?? null,
                    'city' => $office['town'] ?? null,
                    'zip_code' => $office['zipCode'] ?? null,
                    'province' => $office['province'] ?? null,
                    'region' => $office['region']['description'] ?? null,
                    'founded_at' => $data['startDate'] ?? null,
                ],
                'cciaa' => filled($data['cciaa'] ?? null) ? trim($data['cciaa'].' '.($data['reaCode'] ?? '')) : null,
                'activity_status' => $data['activityStatus'] ?? null,
                'address' => $this->formatAddress($office),
            ],
            'shareholders' => collect($shareholders)
                ->map(fn (array $holder): array => [
                    'is_company' => filled($holder['companyName'] ?? null),
                    'name' => $holder['companyName'] ?? $holder['surname'] ?? null,
                    'first_name' => $holder['name'] ?? null,
                    'tax_code' => isset($holder['taxCode']) ? strtoupper(trim((string) $holder['taxCode'])) : null,
                    'percent' => isset($holder['percentShare']) ? (float) $holder['percentShare'] : null,
                ])
                ->filter(fn (array $holder): bool => filled($holder['tax_code']) && filled($holder['name']))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $service, string $vat, string $operation, float $cost, ?Model $subject): array
    {
        $token = config('services.openapi.token');

        if (blank($token)) {
            throw new RuntimeException('Token Openapi non configurato (OPENAPI_TOKEN).');
        }

        $startedAt = microtime(true);
        $request = ['service' => $service, 'vat' => $vat];

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(30)
                ->get(rtrim((string) config('services.openapi.company_url'), '/').'/'.$service.'/'.urlencode($vat));
        } catch (ConnectionException $e) {
            $this->logger->record(ApiProvider::Openapi, $operation, $subject, $request, $startedAt, null, false, error: $e->getMessage());

            throw new RuntimeException('Openapi non raggiungibile.', 0, $e);
        }

        if (! $response->successful() || $response->json('success') === false) {
            $message = $response->json('message') ?: mb_substr($response->body(), 0, 300);
            $this->logger->record(ApiProvider::Openapi, $operation, $subject, $request, $startedAt, $response->status(), false, error: $message);

            throw new RuntimeException('Errore Openapi (HTTP '.$response->status().')'.($message ? ': '.$message : '.'));
        }

        $json = $response->json() ?? [];
        $this->logger->record(
            ApiProvider::Openapi,
            $operation,
            $subject,
            $request,
            $startedAt,
            $response->status(),
            true,
            summary: $operation === 'shareholders' ? count((array) ($json['data'] ?? [])).' soci' : ($this->firstItem($json)['companyName'] ?? 'Nessun dato'),
            cost: $cost,
            response: $json,
        );

        return ['data' => $json['data'] ?? []] + $json;
    }

    /**
     * Ultimo bilancio depositato; se manca, l'ultimo anno con almeno un dato.
     *
     * @param  array<string, mixed>  $data
     * @return array{year: ?int, employees: ?int, turnover: int|float|null, net_worth: int|float|null, share_capital: int|float|null}
     */
    private function lastBalance(array $data): array
    {
        $balance = (array) ($data['balanceSheets']['last'] ?? []);

        if ($balance === []) {
            $balance = (array) collect($data['balanceSheets']['all'] ?? [])->first(fn (array $year): bool => isset($year['turnover']) || isset($year['netWorth']));
        }

        return [
            'year' => $balance['year'] ?? null,
            'employees' => $balance['employees'] ?? null,
            'turnover' => $balance['turnover'] ?? null,
            'net_worth' => $balance['netWorth'] ?? null,
            'share_capital' => $balance['shareCapital'] ?? null,
        ];
    }

    private function formatAteco(mixed $code): ?string
    {
        $code = trim((string) $code);

        return preg_match('/^\d{4,6}$/', $code) ? substr($code, 0, 2).'.'.substr($code, 2) : ($code ?: null);
    }

    /**
     * @param  array<string, mixed>  $office
     */
    private function formatAddress(array $office): ?string
    {
        $street = trim(implode(' ', array_filter([$office['toponym'] ?? null, $office['streetName'] ?? $office['street'] ?? null, $office['streetNumber'] ?? null])));
        $place = trim(implode(' ', array_filter([$office['zipCode'] ?? null, $office['town'] ?? null, isset($office['province']) ? '('.$office['province'].')' : null])));

        return implode(', ', array_filter([$street, $place])) ?: null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function firstItem(array $json): array
    {
        $data = $json['data'] ?? [];

        return array_is_list($data) ? (array) ($data[0] ?? []) : $data;
    }
}
