<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycActivitySector: string implements HasLabel
{
    case Commerce = 'commercio_servizi';
    case PublicAdmin = 'pubblica_amministrazione';
    case Construction = 'edilizia';
    case Finance = 'credito_finanza';
    case Industry = 'industria';
    case Tourism = 'turismo';
    case Jewelry = 'gioielli_antiquariato';
    case Waste = 'rifiuti';
    case Renewables = 'energie_rinnovabili';
    case OtherRisk = 'altre_attivita_rischio';
    case None = 'nessuna_condizione';

    public function getLabel(): string
    {
        return match ($this) {
            self::Commerce => 'Commercio e servizi',
            self::PublicAdmin => 'Pubblica amministrazione',
            self::Construction => 'Edilizia',
            self::Finance => 'Credito e finanza',
            self::Industry => 'Industria',
            self::Tourism => 'Turismo',
            self::Jewelry => 'Gioielli, oggetti preziosi e antiquariato',
            self::Waste => 'Raccolta e smaltimento rifiuti',
            self::Renewables => 'Energie rinnovabili',
            self::OtherRisk => 'Altre attività a rischio',
            self::None => 'Nessuna condizione (studente, casalinga, disoccupato)',
        };
    }
}
