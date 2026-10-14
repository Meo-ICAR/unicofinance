<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycPersonPurpose: string implements HasLabel
{
    case Personal = 'personale_familiare';
    case Professional = 'professionale_commerciale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Personal => 'Scopo personale e familiare',
            self::Professional => 'Scopo professionale o commerciale',
        };
    }
}
