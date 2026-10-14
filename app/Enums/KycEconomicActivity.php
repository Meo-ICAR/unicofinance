<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycEconomicActivity: string implements HasLabel
{
    case Employee = 'dipendente';
    case SelfEmployed = 'autonomo';
    case Professional = 'libero_professionista';
    case Entrepreneur = 'imprenditore';
    case Retired = 'pensionato';
    case NonProfessional = 'non_professionale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Employee => 'Lavoratore dipendente',
            self::SelfEmployed => 'Lavoratore autonomo',
            self::Professional => 'Libero professionista',
            self::Entrepreneur => 'Imprenditore',
            self::Retired => 'Pensionato',
            self::NonProfessional => 'Attività non professionale (casalinga, studente, disoccupato)',
        };
    }
}
