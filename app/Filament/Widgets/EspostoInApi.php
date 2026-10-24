<?php

namespace App\Filament\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Widget la cui tabella è disponibile anche come API per unicobpm (GET /api/widgets/{widget}): stessa query della dashboard, senza
 * i filtri predefiniti dell'utente, globale oppure limitata a un istruttore o a un agente a seconda del widget.
 */
interface EspostoInApi
{
    /** Nome del parametro della richiesta che limita la tabella: `employee_id` (istruttore) o `fornitori_id` (agente). */
    public static function parametroApi(): string;

    /** Con il parametro vuoto: tutte le righe (per gli agenti il parametro è obbligatorio). */
    public static function queryApi(?int $id): Builder;

    /**
     * @return array<string, mixed>
     */
    public static function rigaApi(Model $record): array;
}
