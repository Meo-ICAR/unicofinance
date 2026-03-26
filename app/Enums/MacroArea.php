<?php

namespace App\Enums;

enum MacroArea: string
{
    case GOVERNANCE = 'Governance';
    case BUSINESS_COMMERCIALE = 'Business / Commerciale';
    case SUPPORTO = 'Supporto';
    case CONTROLLI_2_LIVELLO = 'Controlli (II Livello)';
    case CONTROLLI_3_LIVELLO = 'Controlli (III Livello)';
    case CONTROLLI_PRIVACY = 'Controlli / Privacy';

    public function getLabel(): string
    {
        return $this->value;
    }
}
