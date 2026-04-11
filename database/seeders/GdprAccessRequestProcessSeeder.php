<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessMacroCategory;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GdprAccessRequestProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company dynamically
        $company = Company::first();

        if (!$company) {
            $this->command->error('Nessuna azienda trovata. Eseguire prima il CompanySeeder.');
            return;
        }

        // Get dynamic references
        $verificationFunction = BusinessFunction::where('code', 'CTRL-COMPL')->first();
        $extractionFunction = BusinessFunction::where('code', 'SUP-IT')->first();
        $macroCategory = ProcessMacroCategory::where('code', 'COMPL')->first();

        if (!$verificationFunction || !$extractionFunction || !$macroCategory) {
            $this->command->error('Business functions or macro category not found. Run BusinessFunctionSeeder and ProcessMacroCategorySeeder first.');
            return;
        }

        // 1. Creazione del Processo per le Richieste di Accesso
        $process = Process::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Gestione Diritto di Accesso (Art. 15 GDPR)',
            ],
            [
                'description' => "Iter procedurale per evadere le richieste di accesso ai dati, garantendo il recupero, la redazione e l'invio sicuro di tutte le informazioni trattate entro i termini SLA.",
                'target_model' => 'App\Models\RequestRegistry',
                'business_function_id' => $verificationFunction->id,
                'process_macro_category_id' => $macroCategory->id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $processId = $process->id;

        // 2. Creazione dei Task associati (Le "Scrivanie" del processo)
        $tasks = [
            [
                'company_id' => $company->id,
                'process_id' => $processId,
                'business_function_id' => $verificationFunction->id,
                'sequence_number' => 1,
                'name' => 'Verifica Identità e Ambito della Richiesta',
                'description' => "Accertamento dell'identità del richiedente (o del mandato) e analisi del perimetro dei dati da estrarre.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'process_id' => $processId,
                'business_function_id' => $extractionFunction->id,
                'sequence_number' => 2,
                'name' => 'Estrazione Dati e Oscuramento',
                'description' => 'Raccolta tecnica dei dati dai vari DB aziendali e oscuramento (redaction) di eventuali dati appartenenti a terzi.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'process_id' => $processId,
                'business_function_id' => $verificationFunction->id,
                'sequence_number' => 3,
                'name' => "Consegna Sicura all'Interessato",
                'description' => 'Predisposizione del pacchetto crittografato e invio tracciato al richiedente.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $createdTasks = [];
        foreach ($tasks as $taskData) {
            $task = ProcessTask::updateOrCreate(
                [
                    'company_id' => $taskData['company_id'],
                    'process_id' => $taskData['process_id'],
                    'name' => $taskData['name'],
                ],
                $taskData
            );
            $createdTasks[] = $task;
        }

        // 3. Creazione delle Checklist per i Task
        $checklistTemplates = [
            'Checklist Preliminare Legale',
            'Checklist IT per Estrazione',
            'Checklist di Consegna e Chiusura',
        ];

        $createdChecklists = [];
        foreach ($createdTasks as $index => $task) {
            $checklist = Checklist::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'process_task_id' => $task->id,
                    'name' => $checklistTemplates[$index],
                ],
                [
                    'sort_order' => 1,
                ]
            );
            $createdChecklists[] = $checklist;
        }

        // 4. Creazione degli Items (Le singole spunte operative)
        $checklistItemTemplates = [
            // Items per Task 1 (Checklist 0)
            [
                'instruction' => "Verificare la validità del documento d'identità o della procura legale allegata.",
                'is_mandatory' => 1,
                'sort_order' => 1,
            ],
            [
                'instruction' => 'Controllare la scadenza SLA dei 30 giorni e impostare eventuale alert.',
                'is_mandatory' => 1,
                'sort_order' => 2,
            ],
            // Items per Task 2 (Checklist 1)
            [
                'instruction' => 'Estrarre il record completo dal CRM (Anagrafica, Consensi, Log accessi).',
                'is_mandatory' => 1,
                'sort_order' => 1,
            ],
            [
                'instruction' => 'Estrarre eventuali ticket di assistenza o registrazioni vocali associate.',
                'is_mandatory' => 0,
                'sort_order' => 2,
            ],
            [
                'instruction' => 'Oscurare i nominativi o i dati sensibili di terze persone presenti nei documenti.',
                'is_mandatory' => 1,
                'sort_order' => 3,
            ],
            // Items per Task 3 (Checklist 2)
            [
                'instruction' => 'Generare archivio .ZIP protetto da password.',
                'is_mandatory' => 1,
                'sort_order' => 1,
            ],
            [
                'instruction' => 'Inviare la password tramite canale separato (es. SMS se file inviato via email).',
                'is_mandatory' => 1,
                'sort_order' => 2,
            ],
            [
                'instruction' => 'Aggiornare lo status nel Registro Richieste in "Evasa" compilando la data di risposta.',
                'is_mandatory' => 1,
                'sort_order' => 3,
            ],
        ];

        // Map checklist items to their respective checklists
        $itemMapping = [
            0 => [0, 1],  // Checklist 0 gets items 0,1
            1 => [2, 3, 4],  // Checklist 1 gets items 2,3,4
            2 => [5, 6, 7],  // Checklist 2 gets items 5,6,7
        ];

        foreach ($createdChecklists as $checklistIndex => $checklist) {
            if (isset($itemMapping[$checklistIndex])) {
                foreach ($itemMapping[$checklistIndex] as $itemIndex) {
                    if (isset($checklistItemTemplates[$itemIndex])) {
                        $itemData = $checklistItemTemplates[$itemIndex];
                        ChecklistItem::firstOrCreate(
                            [
                                'company_id' => $company->id,
                                'checklist_id' => $checklist->id,
                                'instruction' => $itemData['instruction'],
                            ],
                            array_merge($itemData, [
                                'created_at' => now(),
                                'updated_at' => now(),
                            ])
                        );
                    }
                }
            }
        }

        $this->command->info('GDPR Access Request Process seeded successfully!');
    }
}
