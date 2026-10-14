<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycRiskLevel: string implements HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Basso',
            self::Medium => 'Medio',
            self::High => 'Alto',
        };
    }
}
