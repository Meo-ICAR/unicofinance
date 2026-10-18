<?php

namespace App\Services\Istruttoria;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use Unico\Core\Models\Employee;

/**
 * Crea l'utente di accesso di un istruttore e lo collega al dipendente (`employees.user_id`). Se esiste già un utente
 * con la stessa email lo collega. La password iniziale è «password»: l'istruttore la cambia con il recupero password.
 */
class CreaUtenteIstruttore
{
    /**
     * @throws RuntimeException se il dipendente ha già un utente, non è attivo o manca l'email
     */
    public function crea(Employee $dipendente): User
    {
        if ($dipendente->user_id !== null) {
            throw new RuntimeException("L'istruttore ha già un utente collegato.");
        }

        if (! $dipendente->is_active || $dipendente->deleted_at !== null) {
            throw new RuntimeException("L'istruttore non è attivo.");
        }

        $email = Str::lower(trim((string) $dipendente->email));

        if ($email === '') {
            throw new RuntimeException("Indica l'email dell'istruttore: è il suo login.");
        }

        $user = User::query()->where('email', $email)->first() ?? User::query()->create([
            'name' => $dipendente->name,
            'email' => $email,
            'password' => 'password',
            'role' => 'employee',
            'profile_type' => 'employee',
            'profile_id' => $dipendente->getKey(),
            'company_id' => $dipendente->company_id,
        ]);

        $dipendente->forceFill(['user_id' => $user->getKey()])->save();

        return $user;
    }
}
