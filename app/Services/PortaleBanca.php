<?php

namespace App\Services;

use Unico\Core\Models\Cliente;

/** Portale della banca per l'immissione della pratica: indirizzo dedicato, altrimenti il sito dell'istituto. */
class PortaleBanca
{
    public static function url(int|string|null $clientiId): ?string
    {
        if (blank($clientiId)) {
            return null;
        }

        $banca = Cliente::query()->find($clientiId);
        $url = $banca?->portal_url ?: $banca?->website;

        if (blank($url)) {
            return null;
        }

        return str_starts_with((string) $url, 'http') ? (string) $url : 'https://'.$url;
    }
}
