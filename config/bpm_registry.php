<?php

use App\Actions\ActivateEmployeeAction;
use App\Actions\ArchiveOldContractsAction;
use App\Actions\Custom\SendDataToSapAction;
use App\Conditions\HasHireDateCondition;
use App\Rules\Bpm\Actions\ApproveCandidateAction;
use App\Rules\Bpm\Actions\GenerateAgentContractAction;
use App\Rules\Bpm\Actions\NotifyProformaUploaderAction;
use App\Rules\Bpm\Actions\ProvisionITAccountsAction;
use App\Rules\Bpm\Actions\SendWelcomeEmailAction;
use App\Rules\Bpm\Actions\ValidateCommissionsTotalAction;
use App\Rules\Bpm\Actions\ValidateOamRegistrationAction;

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

            // ─── Proforma / Invoice Actions ───
        ValidateCommissionsTotalAction::class => [
            'name' => '✅ Verifica Quadratura Commissioni',
            'group' => 'Contabilità',
            'companies' => null,
        ],
        NotifyProformaUploaderAction::class => [
            'name' => '📧 Notifica Emissione Fattura',
            'group' => 'Contabilità',
            'companies' => null,
        ],

            // ─── Agent Recruitment / Onboarding Actions ───
        ApproveCandidateAction::class => [
            'name' => '✅ Approva Candidato',
            'group' => 'Reclutamento Agenti',
            'companies' => null,
        ],
        ValidateOamRegistrationAction::class => [
            'name' => '🔍 Verifica Iscrizione OAM',
            'group' => 'Reclutamento Agenti',
            'companies' => null,
        ],
        GenerateAgentContractAction::class => [
            'name' => '📄 Genera Contratto Agente',
            'group' => 'Reclutamento Agenti',
            'companies' => null,
        ],
        ProvisionITAccountsAction::class => [
            'name' => '💻 Attiva Account IT',
            'group' => 'Reclutamento Agenti',
            'companies' => null,
        ],
        SendWelcomeEmailAction::class => [
            'name' => '📧 Invia Email di Benvenuto',
            'group' => 'Reclutamento Agenti',
            'companies' => null,
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
