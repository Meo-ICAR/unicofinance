<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ApiProvider: string implements HasLabel
{
    case Cerved = 'cerved';
    case SanctionsIo = 'sanctions_io';
    case Openapi = 'openapi';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cerved => 'Cerved',
            self::SanctionsIo => 'sanctions.io (PEP)',
            self::Openapi => 'Openapi (dati societari)',
        };
    }

    /**
     * Costo unitario di una chiamata riuscita, da configurazione.
     */
    public function unitCost(): float
    {
        return (float) config(match ($this) {
            self::Cerved => 'services.cerved.cost',
            self::SanctionsIo => 'services.aml.cost',
            self::Openapi => 'services.openapi.cost_advanced',
        }, 0);
    }
}
