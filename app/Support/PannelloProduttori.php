<?php

namespace App\Support;

use Filament\Facades\Filament;

/** Il pannello a cui accedono solo i produttori (agenti): vedono le proprie pratiche e i propri compensi, non quelli dell'istituto. */
class PannelloProduttori
{
    public static function attivo(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'agenti';
    }
}
