<?php

namespace App\Enums;

enum OutsourcableStatus: string
{
    case YES = 'yes';
    case NO = 'no';
    case PARTIAL = 'partial';

    public function getLabel(): string
    {
        return match($this) {
            self::YES => 'Sì',
            self::NO => 'No',
            self::PARTIAL => 'Parziale',
        };
    }
}
