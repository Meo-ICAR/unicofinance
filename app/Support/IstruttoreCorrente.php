<?php

namespace App\Support;

use Unico\Core\Models\Employee;

/** L'istruttore (scheda dipendente) collegato all'utente che usa il pannello. */
class IstruttoreCorrente
{
    public static function id(): ?int
    {
        $userId = auth()->id();

        if ($userId === null) {
            return null;
        }

        $id = Employee::query()->where('user_id', $userId)->value('id');

        return $id === null ? null : (int) $id;
    }
}
