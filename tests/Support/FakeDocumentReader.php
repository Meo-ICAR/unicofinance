<?php

namespace Tests\Support;

use Illuminate\Support\Collection;
use Unico\Core\Documents\Contracts\DocumentReader;

/** Lettore AI finto: risponde in base al profilo di lettura, senza rete. */
class FakeDocumentReader implements DocumentReader
{
    /** @var array<string, array<string, mixed>> */
    public array $overrides = [];

    public function enabled(): bool
    {
        return true;
    }

    public function read(string $kind, string $mime, string $bytes): ?array
    {
        $base = ['legible' => true, 'kind_detected' => $kind, 'surname' => null, 'name' => null, 'fiscal_code' => null,
            'birth_date' => null, 'birth_place' => null, 'document_number' => null, 'expiry_date' => null, 'document_type' => null,
            'employer' => null, 'period' => null, 'net_amount' => null, 'notes' => null, 'matches_template' => null, 'signed' => null];

        $fields = match ($kind) {
            'informativa' => ['matches_template' => true, 'signed' => true],
            'identita' => ['surname' => 'Rossi', 'name' => 'Mario', 'fiscal_code' => 'RSSMRA80A01H501U', 'birth_date' => '01/01/1980', 'document_type' => 'carta_identita', 'document_number' => 'AX123', 'expiry_date' => '01/01/2040'],
            'codice_fiscale' => ['surname' => 'Rossi', 'name' => 'Mario', 'fiscal_code' => 'RSSMRA80A01H501U'],
            'questionario_kyc' => ['surname' => 'Rossi', 'name' => 'Mario', 'fiscal_code' => 'RSSMRA80A01H501U', 'birth_date' => '01/01/1980', 'birth_place' => 'Roma', 'citizenship' => 'Italia',
                'address_street' => 'Via Verdi 7', 'address_city' => 'Roma', 'address_province' => 'RM', 'address_zip' => '00100', 'signed' => true,
                'pep_status' => 'nessuna', 'economic_activity' => 'autonomo', 'activity_sector' => 'commercio_servizi', 'activity_location' => 'regione_residenza',
                'financing_nature' => 'prestito_personale', 'financing_purpose' => 'personale_familiare', 'income_band' => 'fino_100k', 'wealth_band' => 'fino_500k'],
            default => [],
        };

        return array_replace($base, $fields, $this->overrides[$kind] ?? []);
    }

    public function classify(Collection $candidates, string $mime, string $bytes): ?array
    {
        return null;
    }

    public function lastUsage(): ?array
    {
        return null;
    }
}
