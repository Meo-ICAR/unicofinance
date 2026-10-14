<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycGeographicArea: string implements HasLabel
{
    case Italy = 'italia';
    case Eu = 'ue';
    case NonEu = 'extra_ue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Italy => 'Italia',
            self::Eu => 'Unione Europea',
            self::NonEu => 'Extra Unione Europea',
        };
    }
}
