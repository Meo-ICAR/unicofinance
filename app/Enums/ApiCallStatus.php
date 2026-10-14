<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApiCallStatus: string implements HasColor, HasLabel
{
    case Success = 'success';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Success => 'Riuscita',
            self::Failed => 'Fallita',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }
}
