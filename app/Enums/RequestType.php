<?php

namespace App\Enums;

enum RequestType: string
{
    case ACCESSO = 'accesso';
    case CANCELLAZIONE = 'cancellazione';
    case RETTIFICA = 'rettifica';
    case OPPOSIZIONE = 'opposizione';
    case LIMITAZIONE = 'limitazione';
    case PORTABILITA = 'portabilita';
    case REVOCA_CONSENSO = 'revoca_consenso';
    case RECLAMAZIONE = 'reclamazione';

    public function label(): string
    {
        return match ($this) {
            self::ACCESSO => 'Accesso (Art. 15)',
            self::CANCELLAZIONE => 'Cancellazione / Oblio (Art. 17)',
            self::RETTIFICA => 'Rettifica (Art. 16)',
            self::OPPOSIZIONE => 'Opposizione (Art. 21)',
            self::LIMITAZIONE => 'Limitazione del Trattamento (Art. 18)',
            self::PORTABILITA => 'Portabilità dei Dati (Art. 20)',
            self::REVOCA_CONSENSO => 'Revoca del Consenso',
            self::RECLAMAZIONE => 'Reclamazione (Art. 77)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::ACCESSO => 'Accesso',
            self::CANCELLAZIONE => 'Cancellazione',
            self::RETTIFICA => 'Rettifica',
            self::OPPOSIZIONE => 'Opposizione',
            self::LIMITAZIONE => 'Limitazione',
            self::PORTABILITA => 'Portabilità',
            self::REVOCA_CONSENSO => 'Revoca Consenso',
            self::RECLAMAZIONE => 'Reclamazione',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->toArray();
    }

    public static function shortOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->shortLabel()])->toArray();
    }
}
