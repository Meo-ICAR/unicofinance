<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrivacyProcessesSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::first()->id ?? null;
        if (!$companyId) {
            $this->command->warn('Nessuna company trovata. Il seeder non verrà eseguito.');
            return;
        }

        // Assicuriamoci che esistano alcune funzioni aziendali di base necessarie per la privacy
        $hrFunction = BusinessFunction::firstOrCreate(
            ['code' => 'HR', 'company_id' => $companyId],
            [
                'name' => 'Risorse Umane', 
                'macro_area' => \App\Enums\MacroArea::SUPPORTO, 
                'type' => \App\Enums\BusinessFunctionType::SUPPORTO
            ]
        );

        $legalFunction = BusinessFunction::firstOrCreate(
            ['code' => 'LEGAL', 'company_id' => $companyId],
            [
                'name' => 'Ufficio Legale & Compliance', 
                'macro_area' => \App\Enums\MacroArea::SUPPORTO, 
                'type' => \App\Enums\BusinessFunctionType::SUPPORTO
            ]
        );

        $dpoFunction = BusinessFunction::firstOrCreate(
            ['code' => 'DPA', 'company_id' => $companyId],
            [
                'name' => 'Data Protection Officer', 
                'macro_area' => \App\Enums\MacroArea::SUPPORTO, 
                'type' => \App\Enums\BusinessFunctionType::CONTROLLO
            ]
        );

        $itFunction = BusinessFunction::firstOrCreate(
            ['code' => 'IT', 'company_id' => $companyId],
            [
                'name' => 'Information Technology', 
                'macro_area' => \App\Enums\MacroArea::SUPPORTO, 
                'type' => \App\Enums\BusinessFunctionType::SUPPORTO
            ]
        );

        // ---- 1. Processo: Assunzione Nuovo Dipendente ----
        $processHr = Process::firstOrCreate(
            ['name' => 'Assunzione Nuovo Dipendente e Onboarding Privacy', 'company_id' => $companyId],
            [
                'description' => 'Procedura standard per la contrattualizzazione di un dipendente, comprendente gli adempimenti privacy, informative e setup utenze.',
                'business_function_id' => $hrFunction->id,
                'is_active' => true,
            ]
        );

        $tasksHr = [
            [
                'name' => 'Raccolta documenti e firma contratto',
                'description' => 'Acquisizione del CV e documenti anagrafici. Sottoscrizione del contratto di assunzione.',
                'business_function_id' => $hrFunction->id,
                'raci' => ['R' => 'HR', 'A' => 'HR', 'C' => 'LEGAL', 'I' => 'IT'],
                'checklists' => [
                    [
                        'name' => 'Documentazione Contrattuale',
                        'items' => [
                            ['instruction' => 'Copia documento d\'identità valido', 'mandatory' => true],
                            ['instruction' => 'Copia codice fiscale', 'mandatory' => true],
                            ['instruction' => 'Firma contratto di assunzione', 'mandatory' => true],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Consegna Informativa Privacy Dipendenti',
                'description' => 'Porgere al dipendente l\'informativa ex Art. 13 GDPR e raccolta firma per presa visione.',
                'business_function_id' => $hrFunction->id,
                'raci' => ['R' => 'HR', 'A' => 'DPA', 'C' => 'LEGAL', 'I' => 'HR'],
                'checklists' => [
                    [
                        'name' => 'Compliance Informativa',
                        'items' => [
                            ['instruction' => 'Stampa informativa Art. 13 GDPR dipendenti', 'mandatory' => true],
                            ['instruction' => 'Acquisizione firma per ricevuta', 'mandatory' => true],
                            ['instruction' => 'Archiviazione copia firmata nel fascicolo personale', 'mandatory' => true],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Lettera d\'incarico Soggetto Autorizzato',
                'description' => 'Compilazione e firma del modulo di autorizzazione al trattamento dei dati per la specifica mansione.',
                'business_function_id' => $hrFunction->id,
                'raci' => ['R' => 'HR', 'A' => 'DPA', 'C' => 'LEGAL', 'I' => 'IT'],
                'checklists' => [
                    [
                        'name' => 'Autorizzazione al Trattamento',
                        'items' => [
                            ['instruction' => 'Identificazione dei database/applicativi accessibili', 'mandatory' => true],
                            ['instruction' => 'Firma lettera di incarico e istruzioni operative', 'mandatory' => true],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Abilitazione Utenze IT e configurazione PC',
                'description' => 'Creazione account Microsoft365/VPN e applicazione permessi come da policy di sicurezza.',
                'business_function_id' => $itFunction->id,
                'raci' => ['R' => 'IT', 'A' => 'IT', 'C' => 'DPA', 'I' => 'HR'],
                'checklists' => [
                    [
                        'name' => 'Setup Tecnico',
                        'items' => [
                            ['instruction' => 'Creazione account Active Directory / Azure AD', 'mandatory' => true],
                            ['instruction' => 'Attivazione MFA (Multi-Factor Authentication)', 'mandatory' => true],
                            ['instruction' => 'Configurazione BitLocker / Crittografia disco PC', 'mandatory' => true],
                        ]
                    ]
                ]
            ]
        ];

        $this->seedTasks($processHr, $tasksHr, $companyId);

        // ---- 2. Processo: Nomina Consulente Esterno ----
        $processDpa = Process::firstOrCreate(
            ['name' => 'Nomina Fornitore in qualità di Responsabile del Trattamento', 'company_id' => $companyId],
            [
                'description' => 'Analisi e onboarding per un nuovo consulente IT o in outsourcing, inclusiva della stesura e firma del DPA (Data Processing Agreement) art. 28 GDPR.',
                'business_function_id' => $legalFunction->id,
                'is_active' => true,
            ]
        );

        $tasksDpa = [
            [
                'name' => 'Vendor Security Assessment',
                'description' => 'Valutazione dell\'affidabilità tecnica del fornitore (ispezioni logiche, certificazioni ISO27001) per testare l\'affidabilità.',
                'business_function_id' => $itFunction->id,
                'raci' => ['R' => 'IT', 'A' => 'DPA', 'C' => 'LEGAL', 'I' => 'HR'],
                'checklists' => [
                    [
                        'name' => 'Assessment Sicurezza',
                        'items' => [
                            ['instruction' => 'Verifica presenza certificazione ISO 27001 / SOC2', 'mandatory' => false],
                            ['instruction' => 'Checklist misure minime di sicurezza (Crittografia, Backup, Log)', 'mandatory' => true],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Stesura o Verifica del DPA (Art. 28)',
                'description' => 'Preparazione dell\'accordo di nomina ex art. 28 GDPR e verifica clausole di sub-responsabilità (SCC, se extra-UE).',
                'business_function_id' => $legalFunction->id,
                'raci' => ['R' => 'LEGAL', 'A' => 'DPA', 'C' => 'IT', 'I' => 'DPA'],
                'checklists' => [
                    [
                        'name' => 'Predisposizione Contrattuale',
                        'items' => [
                            ['instruction' => 'Verifica clausole art. 28 GDPR', 'mandatory' => true],
                            ['instruction' => 'Verifica ubicazione data center (extra-UE?)', 'mandatory' => true],
                            ['instruction' => 'Approvazione DPO / DPA', 'mandatory' => true],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Firma Accordo e Registrazione nel Registro Trattamenti',
                'description' => 'Firma bilaterale del DPA e aggiornamento del Registro Esterne.',
                'business_function_id' => $legalFunction->id,
                'raci' => ['R' => 'LEGAL', 'A' => 'LEGAL', 'C' => 'DPA', 'I' => 'IT'],
                'checklists' => [
                    [
                        'name' => 'Chiusura Iter',
                        'items' => [
                            ['instruction' => 'Scambio contratti firmati digitalmente', 'mandatory' => true],
                            ['instruction' => 'Aggiornamento Registro dei Responsabili Esterni', 'mandatory' => true],
                        ]
                    ]
                ]
            ]
        ];

        $this->seedTasks($processDpa, $tasksDpa, $companyId);
        
        $this->command->info('Processi, Task, Matrici RACI e Checklist popolati con successo!');
    }

    private function seedTasks($process, $tasks, $companyId)
    {
        foreach ($tasks as $index => $t) {
            $task = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'name' => $t['name']],
                [
                    'company_id' => $companyId,
                    'business_function_id' => $t['business_function_id'],
                    'description' => $t['description'] ?? null,
                    'sequence_number' => $index + 1,
                ]
            );

            // Popola matrice RACI 
            foreach ($t['raci'] as $role => $funcCode) {
                $func = BusinessFunction::where('code', $funcCode)->where('company_id', $companyId)->first();
                if ($func) {
                    RaciAssignment::updateOrCreate(
                        ['process_task_id' => $task->id, 'role' => $role, 'company_id' => $companyId],
                        ['business_function_id' => $func->id]
                    );
                }
            }

            // Popola Checklist
            if (isset($t['checklists'])) {
                foreach ($t['checklists'] as $cIdx => $c) {
                    $checklist = Checklist::updateOrCreate(
                        ['process_task_id' => $task->id, 'name' => $c['name']],
                        ['sort_order' => $cIdx + 1]
                    );

                    foreach ($c['items'] as $iIdx => $i) {
                        ChecklistItem::updateOrCreate(
                            ['checklist_id' => $checklist->id, 'instruction' => $i['instruction']],
                            [
                                'is_mandatory' => $i['mandatory'] ?? true,
                                'sort_order' => $iIdx + 1
                            ]
                        );
                    }
                }
            }
        }
    }
}
