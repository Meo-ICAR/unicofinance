<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;

/** Stadio di una provvigione dell'agente, dallo stato del compenso in Proforma: senza stato è in maturazione, «Inserito» è maturata, «Pagato» è pagata. */
enum MaturazioneProvvigione: string implements HasColor, HasLabel
{
    case InMaturazione = 'in_maturazione';
    case Maturata = 'maturata';
    case Pagata = 'pagata';

    public function getLabel(): string
    {
        return match ($this) {
            self::InMaturazione => 'In maturazione',
            self::Maturata => 'Maturata',
            self::Pagata => 'Pagata',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InMaturazione => 'gray',
            self::Maturata => 'success',
            self::Pagata => 'info',
        };
    }

    public function applica(Builder $query): Builder
    {
        return match ($this) {
            self::InMaturazione => $query->whereNull('stato'),
            self::Maturata => $query->where('stato', 'Inserito'),
            self::Pagata => $query->where('stato', 'Pagato'),
        };
    }

    public static function da(?string $stato): self
    {
        return match ($stato) {
            'Inserito' => self::Maturata,
            'Pagato' => self::Pagata,
            default => self::InMaturazione,
        };
    }
}
