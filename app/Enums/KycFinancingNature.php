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
}
