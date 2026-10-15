<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Dati della scheda cliente che dipendono dal tipo di finanziamento dell'ultima pratica.
 * La mappa tipo => sezioni è in `FinancingType::sections()`.
 */
enum FinancingSection: string implements HasLabel
{
    case ClienteDatiNascita = 'cliente_dati_nascita';
    case ClienteDatoreLavoro = 'cliente_datore_lavoro';
    case ClienteReddito = 'cliente_reddito';
    case ClienteQuotaCedibile = 'cliente_quota_cedibile';
    case ClienteDatiSocietari = 'cliente_dati_societari';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClienteDatiNascita => 'Cliente: data e luogo di nascita, sesso, cittadinanza',
            self::ClienteDatoreLavoro => 'Cliente: datore di lavoro / ente',
            self::ClienteReddito => 'Cliente: reddito netto mensile (stipendio o pensione)',
            self::ClienteQuotaCedibile => 'Cliente: quota cedibile',
            self::ClienteDatiSocietari => 'Cliente: dati societari, legale rappresentante, ultimo bilancio',
        };
    }

    /** Senza tipo di finanziamento riconosciuto si vede tutto. */
    public function isVisibleFor(?FinancingType $type): bool
    {
        return $type === null || $type->shows($this);
    }
}
