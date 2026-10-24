<?php

namespace App\Filament\Pages;

use App\Filament\Agenti\Pages\Simulazione as SimulazioneAgenti;
use App\Models\User;

/**
 * La simulazione del portale agenti anche nel pannello admin, per chi è insieme produttore e istruttore e lavora solo qui: stesso
 * percorso (preventivo, perfezionamento, firma, invio), con le pratiche del produttore collegato all'utente. Compare solo a chi ha un produttore.
 */
class Simulazione extends SimulazioneAgenti
{
    protected static string|\UnitEnum|null $navigationGroup = 'Pratiche';

    public static function canAccess(): bool
    {
        $utente = auth()->user();

        return $utente instanceof User && $utente->fornitore()->exists();
    }
}
