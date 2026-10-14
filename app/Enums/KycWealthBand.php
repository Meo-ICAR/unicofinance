<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycWealthBand: string implements HasLabel
{
    case Up500k = 'fino_500k';
    case From500kTo2500k = '500k_2500k';
    case Over2500k = 'oltre_2500k';

    public function getLabel(): string
    {
        return match ($this) {
            self::Up500k => 'Fino a 500.000 euro',
            self::From500kTo2500k => 'Da 500.000 a 2.500.000 euro',
            self::Over2500k => 'Oltre 2.500.000 euro',
        };
    }
}
