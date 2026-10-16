<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crea l'utente di accesso di un cliente persona fisica e lo collega alla scheda (`clients.user_id`). Se esiste già un utente
 * con la stessa email lo collega. La password è casuale: il cliente la imposta con il recupero password.
 */
class CreaUtenteCliente
{
    /**
     * @throws RuntimeException se il cliente non è una persona fisica, ha già un utente o manca l'email
     */
    public function crea(Client $cliente): User
    {
        if (! $cliente->is_person) {
            throw new RuntimeException("L'utente si crea solo per le persone fisiche.");
        }

        if ($cliente->user_id !== null) {
            throw new RuntimeException('Il cliente ha già un utente collegato.');
        }

        $email = Str::lower(trim((string) $cliente->email));

        if ($email === '') {
            throw new RuntimeException("Indica e salva l'email del cliente: è il suo login.");
        }

        $user = User::query()->where('email', $email)->first() ?? User::query()->create([
            'name' => trim(($cliente->first_name ?? '').' '.$cliente->name),
            'email' => $email,
            'password' => Str::random(40),
            'role' => 'client',
            'profile_type' => 'client',
            'profile_id' => $cliente->getKey(),
            'company_id' => $cliente->company_id,
        ]);

        $cliente->forceFill(['user_id' => $user->getKey()])->save();

        return $user;
    }
}
