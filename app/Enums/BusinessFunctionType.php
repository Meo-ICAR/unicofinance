<?php

namespace App\Enums;

enum BusinessFunctionType: string
{
    case STRATEGICA = 'Strategica';
    case OPERATIVA = 'Operativa';
    case SUPPORTO = 'Supporto';
    case CONTROLLO = 'Controllo';

    public function getLabel(): string
    {
        return $this->value;
    }
}
