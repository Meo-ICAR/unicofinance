<?php

namespace App\Documents\Api;

use App\Services\OpenApiCompanyService;
use Unico\Core\Documents\Api\CompanyDataFetcher;
use Unico\Core\Models\Document;

/** Dati societari richiesti per un documento: la chiamata passa dal servizio dell'app, che ne registra esito e costo. */
class LoggedCompanyDataFetcher extends CompanyDataFetcher
{
    public function __construct(private readonly OpenApiCompanyService $logged) {}

    protected function load(string $vat, Document $document): array
    {
        return $this->logged->fetchCompany($vat, $document->documentable);
    }
}
