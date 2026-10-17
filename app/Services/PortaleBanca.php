<?php

namespace App\Services;

use Unico\Core\Models\Cliente;
use Unico\Core\Models\Website;

/** Portale della banca per l'immissione della pratica: il portale di caricamento dell'istituto, altrimenti il vecchio indirizzo dedicato o il sito. */
class PortaleBanca
{
    public static function url(int|string|null $clientiId): ?string
    {
        if (blank($clientiId)) {
            return null;
        }

        $banca = Cliente::query()->find($clientiId);
        $url = $banca?->portalUrl(Website::PORTAL_CARICAMENTO) ?: $banca?->portal_url ?: $banca?->website;

        if (blank($url)) {
            return null;
        }

        return str_starts_with((string) $url, 'http') ? (string) $url : 'https://'.$url;
    }
}
