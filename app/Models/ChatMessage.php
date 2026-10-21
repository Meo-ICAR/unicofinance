<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\ChatMessage as CoreChatMessage;

/**
 * Conversazione dell'assistente sul database di unicofinance (non sulla connessione core).
 * Il thread è per utente e pannello: il global scope nasconde i messaggi degli altri.
 */
class ChatMessage extends CoreChatMessage
{
    /**
     * Il trait del core, quando il default non è mysql (i test usano sqlite),
     * sposterebbe il modello sulla connessione core. La tabella è dell'app.
     */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $user = auth()->user();

            if ($message->user_id === null && $user !== null) {
                $message->user_id = $user->getKey();
            }

            if ($message->company_id === null && $user !== null && filled($user->company_id)) {
                $message->company_id = $user->company_id;
            }
        });

        static::addGlobalScope('owned', function (Builder $builder): void {
            $userId = auth()->id();

            if ($userId === null) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $builder->where('user_id', $userId);
        });
    }
}
