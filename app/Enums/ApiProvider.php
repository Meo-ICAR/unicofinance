<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ApiProvider: string implements HasLabel
{
    case Cerved = 'cerved';
    case SanctionsIo = 'sanctions_io';
    case Openapi = 'openapi';
    case OpenapiCreditScore = 'openapi_creditscore';
    case Anthropic = 'anthropic';
    case Mediafacile = 'mediafacile';
    case BusinessCentral = 'business_central';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cerved => 'Cerved',
            self::SanctionsIo => 'sanctions.io (PEP)',
            self::Openapi => 'Openapi (dati societari)',
            self::OpenapiCreditScore => 'Openapi (credit score)',
            self::Anthropic => 'AI (lettura documenti)',
            self::Mediafacile => 'Mediafacile (import pratiche e provvigioni)',
            self::BusinessCentral => 'Business Central (invio COGE)',
        };
    }

    /**
     * Costo unitario di una chiamata riuscita, da configurazione.
     */
    public function unitCost(): float
    {
        if ($this === self::Mediafacile || $this === self::BusinessCentral) {
            return 0.0;
        }

        return (float) config(match ($this) {
            self::Cerved => 'services.cerved.cost',
            self::SanctionsIo => 'services.aml.cost',
            self::Openapi => 'services.openapi.cost_advanced',
            self::OpenapiCreditScore => 'services.openapi.cost_creditscore',
            self::Anthropic => 'services.anthropic.cost',
            self::Mediafacile, self::BusinessCentral => throw new \LogicException('Provider senza costo'),
        }, 0);
    }
}
