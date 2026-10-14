<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycCoverage: string implements HasLabel
{
    case Missing = 'missing';
    case Expired = 'expired';
    case Complete = 'complete';

    public function getLabel(): string
    {
        return match ($this) {
            self::Missing => 'Mancante',
            self::Expired => 'Scaduto',
            self::Complete => 'Completo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Missing => 'danger',
            self::Expired => 'warning',
            self::Complete => 'success',
        };
    }
}
