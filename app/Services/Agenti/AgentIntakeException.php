<?php

namespace App\Services\Agenti;

use RuntimeException;

/** Errore di dominio nell'accoglienza di una richiesta: il codice è stabile (lo legge l'app chiamante), il messaggio no. */
class AgentIntakeException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
