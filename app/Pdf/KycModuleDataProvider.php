<?php

namespace App\Pdf;

use App\Models\KycQuestionnaire;
use Unico\Core\Models\Client as CoreClient;
use Unico\Core\Models\Pratica;
use Unico\Core\Pdf\ModuleDataProvider;

/** Valori `kyc.*` del questionario di adeguata verifica (tabella di unicofinance) per compilare e controllare i moduli. */
class KycModuleDataProvider implements ModuleDataProvider
{
    private const FIELDS = [
        'pep_status', 'financing_purpose', 'economic_activity', 'activity_sector', 'activity_location',
        'financing_nature', 'income_band', 'wealth_band', 'legal_nature', 'geographic_area',
    ];

    public function values(Pratica $pratica, ?CoreClient $client): array
    {
        $kyc = $client === null
            ? null
            : KycQuestionnaire::query()->where('client_id', $client->getKey())->latest('id')->first();

        $values = [];

        foreach (self::FIELDS as $field) {
            $value = $kyc?->{$field};
            $values["kyc.{$field}"] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $values;
    }
}
