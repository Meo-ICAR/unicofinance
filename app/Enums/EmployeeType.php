<?php

namespace App\Enums;

enum EmployeeType: string
{
    case DIPENDENTE = 'dipendente';
    case COLLABORATORE = 'collaboratore';
    case STAGISTA = 'stagista';
    case CONSULENTE = 'consulente';
    case AMMINISTRATORE = 'amministratore';

    public function getLabel(): string
    {
        return match($this) {
            self::DIPENDENTE => 'Dipendente',
            self::COLLABORATORE => 'Collaboratore',
            self::STAGISTA => 'Stagista',
            self::CONSULENTE => 'Consulente',
            self::AMMINISTRATORE => 'Amministratore',
        };
    }
}
