<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Complete = 'complete';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Complete => 'Completo',
            self::Approved => 'Approvato',
        };
    }
}
