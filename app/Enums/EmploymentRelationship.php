<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentRelationship: string implements HasLabel
{
    case Statale = 'statale';
    case Pubblico = 'pubblico';
    case PrivatoSpa = 'privato_spa';
    case PrivatoAltraForma = 'privato_altra_forma';
    case PrivatoSmallBusiness = 'privato_small_business';
    case Postale = 'postale';
    case Ferroviere = 'ferroviere';
    case Parapubblico = 'parapubblico';
    case Medico = 'medico';
    case PensionatoInps = 'pensionato_inps';
    case PensionatoInpdap = 'pensionato_inpdap';
    case PensionatoAltriEnti = 'pensionato_altri_enti';

    public function getLabel(): string
    {
        return match ($this) {
            self::Statale => 'Statale',
            self::Pubblico => 'Pubblico',
            self::PrivatoSpa => 'Privato SPA',
            self::PrivatoAltraForma => 'Privato Altra forma',
            self::PrivatoSmallBusiness => 'Privato Small Business',
            self::Postale => 'Postale',
            self::Ferroviere => 'Ferroviere',
            self::Parapubblico => 'Parapubblico',
            self::Medico => 'Medico',
            self::PensionatoInps => 'Pensionato INPS',
            self::PensionatoInpdap => 'Pensionato INPDAP',
            self::PensionatoAltriEnti => 'Pensionato altri enti',
        };
    }

    public function isRetiree(): bool
    {
        return in_array($this, [self::PensionatoInps, self::PensionatoInpdap, self::PensionatoAltriEnti], true);
    }

    /**
     * @return array<int, self>
     */
    public static function allowedFor(?SimulationContractType $contractType): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => ! $case->isRetiree() || $contractType?->allowsRetirees() !== false,
        ));
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(?SimulationContractType $contractType = null): array
    {
        $options = [];

        foreach (self::allowedFor($contractType) as $case) {
            $options[$case->isRetiree() ? 'Pensionati' : 'Dipendenti'][$case->value] = $case->getLabel();
        }

        return $options;
    }
}
