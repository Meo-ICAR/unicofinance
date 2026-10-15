<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Unico\Core\Support\CodiceFiscale;

/** Controlli sul codice fiscale o sulla partita IVA del richiedente, con le routine di unico-core. */
class ControlloRichiedente
{
    /** Codice fiscale per le persone, partita IVA per le società; null se valido o vuoto. */
    public static function taxCodeError(string $value, bool $isPerson): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($isPerson) {
            return CodiceFiscale::parse($value) === null ? 'Il codice fiscale non è valido.' : null;
        }

        return controllaPartitaIva(strtoupper($value));
    }

    /** Avvisa se il codice non è valido o non corrisponde a cognome e nome. */
    public static function warn(?string $state, bool $isPerson, ?string $cognome, ?string $nome): void
    {
        $error = self::taxCodeError((string) $state, $isPerson);

        if ($error !== null) {
            Notification::make()->title($error)->warning()->send();

            return;
        }

        if ($isPerson && filled($state) && filled($cognome) && filled($nome)) {
            $mismatches = CodiceFiscale::mismatches((string) $state, (string) $cognome, (string) $nome);

            if ($mismatches !== []) {
                Notification::make()
                    ->title('Il codice fiscale non corrisponde a nome e cognome')
                    ->body(collect($mismatches)->map(fn (array $m): string => "{$m['field']}: atteso {$m['expected']}, trovato {$m['found']}")->implode(' · '))
                    ->warning()
                    ->send();
            }
        }
    }
}
