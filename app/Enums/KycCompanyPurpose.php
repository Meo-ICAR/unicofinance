<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycCompanyPurpose: string implements HasLabel
{
    case FinancialNeeds = 'fabbisogno_finanziario';
    case ConsortiumGuarantee = 'garanzia_consortile';
    case CreditLine = 'apertura_credito';
    case MortgageSecured = 'mutuo_ipotecario';
    case MortgageUnsecured = 'mutuo_chirografario';
    case ReceivablesAdvance = 'anticipo_crediti';
    case PortfolioDiscount = 'sconto_portafoglio';
    case RealEstateLeasing = 'leasing_immobiliare';
    case EquipmentLeasing = 'leasing_strumentale';
    case Factoring = 'factoring';

    public function getLabel(): string
    {
        return match ($this) {
            self::FinancialNeeds => 'Fabbisogno finanziario',
            self::ConsortiumGuarantee => 'Garanzia consortile',
            self::CreditLine => 'Apertura di credito',
            self::MortgageSecured => 'Mutuo ipotecario',
            self::MortgageUnsecured => 'Mutuo chirografario',
            self::ReceivablesAdvance => 'Anticipo crediti',
            self::PortfolioDiscount => 'Sconto portafoglio',
            self::RealEstateLeasing => 'Leasing immobiliare',
            self::EquipmentLeasing => 'Leasing strumentale',
            self::Factoring => 'Factoring',
        };
    }
}
