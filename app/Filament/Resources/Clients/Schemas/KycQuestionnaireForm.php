<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycCompanyPurpose;
use App\Enums\KycEconomicActivity;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycRiskLevel;
use App\Enums\KycWealthBand;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campi del questionario KYC condivisi dal pannello admin e dal portale agenti.
 * `$required` rende obbligatori i campi: l'admin li compila a piu' riprese, il produttore in un'unica volta.
 */
class KycQuestionnaireForm
{
    /**
     * @return array<string, string>
     */
    public static function purposeOptions(bool $isPerson): array
    {
        $enum = $isPerson ? KycPersonPurpose::class : KycCompanyPurpose::class;

        return collect($enum::cases())
            ->mapWithKeys(fn ($case): array => [$case->value => $case->getLabel()])
            ->all();
    }

    public static function pepStatus(Closure $isPerson, bool $required = false): Radio
    {
        return Radio::make('pep_status')
            ->label('Condizione PEP')
            ->options(KycPepStatus::class)
            ->visible($isPerson)
            ->required($required)
            ->columnSpanFull();
    }

    public static function financingPurpose(Closure $isPerson, bool $required = false): Select
    {
        return Select::make('financing_purpose')
            ->label('Scopo del finanziamento')
            ->options(fn (Get $get): array => self::purposeOptions((bool) $isPerson($get)))
            ->required($required);
    }

    public static function riskLevel(bool $required = false): Select
    {
        return Select::make('risk_level')
            ->label('Livello di rischio')
            ->options(KycRiskLevel::class)
            ->required($required);
    }

    public static function personSection(Closure $isPerson, bool $required = false, ?string $thirdPartyHelper = null, bool $liveThirdParty = false): Section
    {
        return Section::make('Persona fisica')
            ->columns(2)
            ->columnSpanFull()
            ->visible($isPerson)
            ->schema([
                Select::make('economic_activity')->label('Attività economica')->options(KycEconomicActivity::class)->required($required),
                Select::make('activity_sector')->label('Settore di attività')->options(KycActivitySector::class)->required($required),
                Select::make('activity_location')->label('Luogo di svolgimento')->options(KycActivityLocation::class)->required($required),
                Select::make('financing_nature')->label('Natura del finanziamento')->options(KycFinancingNature::class)->required($required),
                Select::make('income_band')->label('Reddito annuo lordo')->options(KycIncomeBand::class)->required($required),
                Select::make('wealth_band')->label('Patrimonio')->options(KycWealthBand::class)->required($required),
                Toggle::make('acts_for_third_party')
                    ->label('Agisce per conto di terzi')
                    ->helperText($thirdPartyHelper)
                    ->live($liveThirdParty),
            ]);
    }

    /**
     * Natura giuridica e area geografica: i campi della persona giuridica comuni ai due pannelli.
     *
     * @return array<int, Select>
     */
    public static function companyFields(bool $required = false): array
    {
        return [
            Select::make('legal_nature')->label('Natura giuridica')->options(KycLegalNature::class)->required($required),
            Select::make('geographic_area')->label('Area geografica')->options(KycGeographicArea::class)->required($required),
        ];
    }
}
