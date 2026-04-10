<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
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

        // 1. Creazione del Processo per le Richieste di Accesso
        $process = Process::updateOrCreate(
            [
                'id' => 29,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'name' => 'Gestione Diritto di Accesso (Art. 15 GDPR)',
            ],
            [
                'description' => "Iter procedurale per evadere le richieste di accesso ai dati, garantendo il recupero, la redazione e l'invio sicuro di tutte le informazioni trattate entro i termini SLA.",
                'target_model' => 'App\Models\RequestRegistry',
                'business_function_id' => 24,  // Verifica Identità
                'process_macro_category_id' => 2,  // COMPL - Processi di Controllo (ID corretto)
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $processId = $process->id;

        // 2. Creazione dei Task associati (Le "Scrivanie" del processo)
        $tasks = [
            [
                'id' => 99,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'process_id' => $processId,
                'business_function_id' => 24,
                'sequence_number' => 1,
                'name' => 'Verifica Identità e Ambito della Richiesta',
                'description' => "Accertamento dell'identità del richiedente (o del mandato) e analisi del perimetro dei dati da estrarre.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 100,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'process_id' => $processId,
                'business_function_id' => 22,  // Estrazione Dati
                'sequence_number' => 2,
                'name' => 'Estrazione Dati e Oscuramento',
                'description' => 'Raccolta tecnica dei dati dai vari DB aziendali e oscuramento (redaction) di eventuali dati appartenenti a terzi.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 101,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'process_id' => $processId,
                'business_function_id' => 24,  // Verifica Identità
                'sequence_number' => 3,
                'name' => "Consegna Sicura all'Interessato",
                'description' => 'Predisposizione del pacchetto crittografato e invio tracciato al richiedente.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($tasks as $task) {
            DB::table('process_tasks')->insert($task);
        }

        // 3. Creazione delle Checklist per i Task
        $checklists = [
            [
                'id' => 99,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'process_task_id' => 99,  // Task 1
                'name' => 'Checklist Preliminare Legale',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 100,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'process_task_id' => 100,  // Task 2
                'name' => 'Checklist IT per Estrazione',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 101,  // Prossimo ID disponibile
                'company_id' => $company->id,
                'process_task_id' => 101,  // Task 3
                'name' => 'Checklist di Consegna e Chiusura',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($checklists as $checklist) {
            DB::table('checklists')->insert($checklist);
        }

        // 4. Creazione degli Items (Le singole spunte operative)
        $checklistItems = [
            // Items per Task 1
            [
                'company_id' => $company->id,
                'checklist_id' => 157,  // Checklist 1
                'instruction' => "Verificare la validità del documento d'identità o della procura legale allegata.",
                'is_mandatory' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'checklist_id' => 157,  // Checklist 1
                'instruction' => 'Controllare la scadenza SLA dei 30 giorni e impostare eventuale alert.',
                'is_mandatory' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Items per Task 2
            [
                'company_id' => $company->id,
                'checklist_id' => 158,  // Checklist 2
                'instruction' => 'Estrarre il record completo dal CRM (Anagrafica, Consensi, Log accessi).',
                'is_mandatory' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'checklist_id' => 158,  // Checklist 2
                'instruction' => 'Estrarre eventuali ticket di assistenza o registrazioni vocali associate.',
                'is_mandatory' => 0,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'checklist_id' => 158,  // Checklist 2
                'instruction' => 'Oscurare i nominativi o i dati sensibili di terze persone presenti nei documenti.',
                'is_mandatory' => 1,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Items per Task 3
            [
                'company_id' => $company->id,
                'checklist_id' => 159,  // Checklist 3
                'instruction' => 'Generare archivio .ZIP protetto da password.',
                'is_mandatory' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'checklist_id' => 159,  // Checklist 3
                'instruction' => 'Inviare la password tramite canale separato (es. SMS se file inviato via email).',
                'is_mandatory' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'checklist_id' => 159,  // Checklist 3
                'instruction' => 'Aggiornare lo status nel Registro Richieste in "Evasa" compilando la data di risposta.',
                'is_mandatory' => 1,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($checklistItems as $item) {
            DB::table('checklist_items')->insert($item);
        }

        $this->command->info('GDPR Access Request Process seeded successfully!');
    }
}
