<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycActivityLocation: string implements HasLabel
{
    case Region = 'regione_residenza';
    case ItalyMulti = 'piu_regioni';
    case Eu = 'unione_europea';
    case NonEu = 'extra_ue';
    case HighRisk = 'paesi_alto_rischio';

    public function getLabel(): string
    {
        return match ($this) {
            self::Region => 'Nella regione di residenza',
            self::ItalyMulti => 'In più regioni italiane',
            self::Eu => 'In paesi dell\'Unione Europea',
            self::NonEu => 'In Stati extracomunitari, esclusi i paesi ad alto rischio',
            self::HighRisk => 'In paesi ad alto rischio',
        };
    }
}
