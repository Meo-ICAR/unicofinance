<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycIncomeBand: string implements HasLabel
{
    case Up100k = 'fino_100k';
    case From100kTo250k = '100k_250k';
    case From250kTo500k = '250k_500k';
    case Over500k = 'oltre_500k';

    public function getLabel(): string
    {
        return match ($this) {
            self::Up100k => 'Fino a 100.000 euro',
            self::From100kTo250k => 'Da 100.000 a 250.000 euro',
            self::From250kTo500k => 'Da 250.000 a 500.000 euro',
            self::Over500k => 'Oltre 500.000 euro',
        };
    }
}
