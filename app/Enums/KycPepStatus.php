<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycPepStatus: string implements HasLabel
{
    case PublicOffice = 'carica_pubblica';
    case DirectFamily = 'familiare';
    case CloseTies = 'stretti_legami';
    case LocalOffice = 'carica_locale';
    case None = 'nessuna';

    public function getLabel(): string
    {
        return match ($this) {
            self::PublicOffice => 'Ricopre o ha ricoperto nell\'ultimo anno un\'importante carica pubblica',
            self::DirectFamily => 'È familiare diretto di una persona che ricopre o ha ricoperto un\'importante carica pubblica',
            self::CloseTies => 'Intrattiene stretti legami con una persona che ricopre o ha ricoperto un\'importante carica pubblica',
            self::LocalOffice => 'Ricopre o ha ricoperto un\'importante carica pubblica locale (PEP locale)',
            self::None => 'Nessuna delle precedenti condizioni',
        };
    }
}
