<?php

namespace App\Support;

/** Voci di compenso di ogni prodotto: ognuna ha la propria percentuale nel listino. */
class VociProvvigionali
{
    /**
     * Prodotto (nome del tipo prodotto) => voce => etichetta, nell'ordine della tabella provvigionale.
     *
     * @var array<string, array<string, string>>
     */
    public const PER_PRODOTTO = [
        'Cessione' => ['standard' => 'Standard', 'special' => 'Special'],
        'Delega' => ['standard' => 'Standard', 'special' => 'Special'],
        'Mutuo' => ['banca' => 'Banca', 'mediazione' => 'Mediazione', 'polizza' => 'Polizza'],
        'Prestito' => ['banca' => 'Banca'],
        'Aziendale' => ['consulenza' => 'Consulenza'],
        'Polizza' => ['premio' => 'Premio', 'brokeraggio' => 'Brokeraggio'],
    ];

    /** @return array<string, string> */
    public static function per(?string $prodotto): array
    {
        return self::PER_PRODOTTO[$prodotto] ?? [];
    }

    /** @return array<string, string> tutte le voci, per i prodotti non riconosciuti */
    public static function tutte(): array
    {
        return array_merge(...array_values(self::PER_PRODOTTO));
    }
}
