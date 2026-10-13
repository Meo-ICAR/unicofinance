<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SimulationContractType: string implements HasLabel
{
    case Cessione = 'cessione';
    case Delega = 'delega';
    case Prestito = 'prestito';
    case Mutuo = 'mutuo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cessione => 'Cessione del quinto',
            self::Delega => 'Delegazione di pagamento',
            self::Prestito => 'Prestito personale',
            self::Mutuo => 'Mutuo',
        };
    }

    /** Valore inviato al preventivatore e nome del tipo prodotto in anagrafica. */
    public function productName(): string
    {
        return match ($this) {
            self::Cessione => 'Cessione',
            self::Delega => 'Delega',
            self::Prestito => 'Prestito',
            self::Mutuo => 'Mutuo',
        };
    }

    public function allowsRetirees(): bool
    {
        return $this !== self::Delega;
    }

    public function asksQuotaCedibile(): bool
    {
        return match ($this) {
            self::Cessione, self::Delega => true,
            self::Prestito, self::Mutuo => false,
        };
    }

    public function asksEmployer(): bool
    {
        return $this === self::Cessione || $this === self::Delega;
    }

    /**
     * @return list<int>
     */
    public function durationChoices(): array
    {
        $biennialMonths = range(24, 120, 12);

        return match ($this) {
            self::Cessione, self::Delega => $biennialMonths,
            self::Prestito => array_merge([12], $biennialMonths),
            self::Mutuo => range(5, 30),
        };
    }

    public function defaultDuration(): ?int
    {
        return match ($this) {
            self::Cessione, self::Delega, self::Prestito => 60,
            self::Mutuo => null,
        };
    }

    public function durationInMonths(int $duration): int
    {
        return $this === self::Mutuo ? $duration * 12 : $duration;
    }

    public function durationLabel(int $duration): string
    {
        return $this === self::Mutuo ? $duration.' anni' : $duration.' mesi';
    }

    /**
     * @return array<int, string>
     */
    public function durationOptions(): array
    {
        $options = [];

        foreach ($this->durationChoices() as $choice) {
            $options[$choice] = $this->durationLabel($choice);
        }

        return $options;
    }
}
