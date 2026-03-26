<?php

namespace App\Enums;

enum SupervisorType: string
{
    case NO = 'no';
    case SI = 'si';
    case FILIALE = 'filiale';

    public function getLabel(): string
    {
        return match($this) {
            self::NO => 'No',
            self::SI => 'Sì (Azienda)',
            self::FILIALE => 'Sì (Filiale)',
        };
    }
}
