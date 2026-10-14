<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycLegalNature: string implements HasLabel
{
    case SoleProprietorship = 'ditta_individuale';
    case CapitalCompany = 'societa_capitali';
    case Partnership = 'societa_persone';
    case Cooperative = 'cooperativa_consorzio';
    case Listed = 'quotata';

    public function getLabel(): string
    {
        return match ($this) {
            self::SoleProprietorship => 'Ditta individuale',
            self::CapitalCompany => 'Società di capitali',
            self::Partnership => 'Società di persone',
            self::Cooperative => 'Cooperativa o consorzio',
            self::Listed => 'Società quotata',
        };
    }
}
