<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycFinancingNature: string implements HasLabel
{
    case SalaryAssignment = 'cessione_quinto';
    case PersonalLoan = 'prestito_personale';
    case Mortgage = 'mutuo';
    case TfsAdvance = 'anticipo_tfs';

    public function getLabel(): string
    {
        return match ($this) {
            self::SalaryAssignment => 'Cessione del quinto dello stipendio/pensione',
            self::PersonalLoan => 'Prestito personale',
            self::Mortgage => 'Mutuo',
            self::TfsAdvance => 'Anticipo TFS/TFR',
        };
    }

    /** La natura del finanziamento corrispondente al tipo prodotto di una pratica (Cessione, Delega, Prestito, Mutuo, TFS...). */
    public static function fromProduct(?string $product): ?self
    {
        return match (mb_strtolower(trim((string) $product))) {
            'cessione', 'delega' => self::SalaryAssignment,
            'prestito', 'prestito inps' => self::PersonalLoan,
            'mutuo', 'mutuo ristrutturazione', 'ipotecario' => self::Mortgage,
            'tfs' => self::TfsAdvance,
            default => null,
        };
    }
}
