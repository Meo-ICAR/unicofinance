<?php

use App\Actions\ActivateEmployeeAction;
use App\Actions\ArchiveOldContractsAction;
use App\Actions\Custom\SendDataToSapAction;
use App\Conditions\HasHireDateCondition;

return [
    /*
     * |--------------------------------------------------------------------------
     * | Azioni (Actions)
     * |--------------------------------------------------------------------------
     */
    'actions' => [
        // Azione Globale (Visibile a tutte le aziende)
        ActivateEmployeeAction::class => [
            'name' => '🟢 Attiva Dipendente',
            'group' => 'Risorse Umane',
            'companies' => null,  // null = visibile a tutti
        ],
        // Azione Globale
        ArchiveOldContractsAction::class => [
            'name' => '🗄️ Archivia Contratti Precedenti',
            'group' => 'Documentale',
            'companies' => null,
        ],
        // Azione CUSTOM (Visibile SOLO all'azienda con ID 4 e 7)
        SendDataToSapAction::class => [
            'name' => '⚙️ Sincronizza dati con SAP',
            'group' => 'Integrazioni Custom',
            'companies' => [4, 7],
        ],
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Condizioni di Salto (Skip Conditions)
     * |--------------------------------------------------------------------------
     */
    'conditions' => [
        HasHireDateCondition::class => [
            'name' => '🚫 Salta se ha già la Data Assunzione',
            'group' => 'Risorse Umane',
            'companies' => null,
        ],
        // ... altre condizioni ...
    ],
];
