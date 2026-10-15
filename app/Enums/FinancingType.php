<?php

namespace App\Enums;

use App\Models\Pratica;
use Filament\Support\Contracts\HasLabel;

/**
 * Famiglia di finanziamento della pratica, dedotta dal tipo prodotto o, in mancanza, dal prodotto.
 */
enum FinancingType: string implements HasLabel
{
    case Cessione = 'cessione';
    case Delega = 'delega';
    case Tfs = 'tfs';
    case Prestito = 'prestito';
    case Mutuo = 'mutuo';
    case Impresa = 'impresa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cessione => 'Cessione del quinto',
            self::Delega => 'Delega di pagamento',
            self::Tfs => 'Anticipo TFS',
            self::Prestito => 'Prestito personale',
            self::Mutuo => 'Mutuo',
            self::Impresa => 'Finanziamento alle imprese',
        };
    }

    /**
     * @return list<FinancingSection>
     */
    public function sections(): array
    {
        return match ($this) {
            self::Cessione, self::Delega => [
                FinancingSection::ClienteDatiNascita,
                FinancingSection::ClienteDatoreLavoro,
                FinancingSection::ClienteReddito,
                FinancingSection::ClienteQuotaCedibile,
            ],
            self::Tfs, self::Prestito, self::Mutuo => [
                FinancingSection::ClienteDatiNascita,
                FinancingSection::ClienteDatoreLavoro,
                FinancingSection::ClienteReddito,
            ],
            self::Impresa => [
                FinancingSection::ClienteDatiSocietari,
            ],
        };
    }

    public function shows(FinancingSection $section): bool
    {
        return in_array($section, $this->sections(), true);
    }

    /** L'ordine conta: "Prestito aziendale" è un finanziamento alle imprese, non un prestito personale. */
    public static function fromProduct(?string $product): ?self
    {
        $product = trim((string) $product);

        if ($product === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/aziend|impres|leasing/i', $product) => self::Impresa,
            (bool) preg_match('/\btfs\b|\btfr\b|fine servizio/i', $product) => self::Tfs,
            (bool) preg_match('/cession|quinto/i', $product) => self::Cessione,
            (bool) preg_match('/deleg/i', $product) => self::Delega,
            (bool) preg_match('/mutu|ipotec/i', $product) => self::Mutuo,
            (bool) preg_match('/prestit|chirograf|microcredit/i', $product) => self::Prestito,
            default => null,
        };
    }

    public static function forPratica(Pratica $pratica): ?self
    {
        return self::fromProduct($pratica->tipoprodotto?->tipo_prodotto ?? $pratica->tipoprodotto?->name)
            ?? self::fromProduct($pratica->denominazione_prodotto);
    }
}
