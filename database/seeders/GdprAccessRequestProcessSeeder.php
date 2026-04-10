<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        // 1. Creazione del Processo per le Richieste di Accesso
        $processId = DB::table('processes')->insertGetId([
            'id' => 13,
            'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
            'business_function_id' => 24,
            'process_macro_category_id' => 2,  // COMPL - Processi di Controllo
            'name' => 'Gestione Diritto di Accesso (Art. 15 GDPR)',
            'description' => "Iter procedurale per evadere le richieste di accesso ai dati, garantendo il recupero, la redazione e l'invio sicuro di tutte le informazioni trattate entro i termini SLA.",
            'target_model' => 'App\Models\RequestRegistry',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Creazione dei Task associati (Le "Scrivanie" del processo)
        $tasks = [
            [
                'id' => 43,
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'process_id' => $processId,
                'business_function_id' => 24,
                'sequence_number' => 1,
                'name' => 'Verifica Identità e Ambito della Richiesta',
                'description' => "Accertamento dell'identità del richiedente (o del mandato) e analisi del perimetro dei dati da estrarre.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 44,
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'process_id' => $processId,
                'business_function_id' => 22,
                'sequence_number' => 2,
                'name' => 'Estrazione Dati e Oscuramento',
                'description' => 'Raccolta tecnica dei dati dai vari DB aziendali e oscuramento (redaction) di eventuali dati appartenenti a terzi.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 45,
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'process_id' => $processId,
                'business_function_id' => 24,
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
                'id' => 43,
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'process_task_id' => 43,
                'name' => 'Checklist Preliminare Legale',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 44,
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'process_task_id' => 44,
                'name' => 'Checklist IT per Estrazione',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 45,
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'process_task_id' => 45,
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
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 43,
                'instruction' => "Verificare la validità del documento d'identità o della procura legale allegata.",
                'is_mandatory' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 43,
                'instruction' => 'Controllare la scadenza SLA dei 30 giorni e impostare eventuale alert.',
                'is_mandatory' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Items per Task 2
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 44,
                'instruction' => 'Estrarre il record completo dal CRM (Anagrafica, Consensi, Log accessi).',
                'is_mandatory' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 44,
                'instruction' => 'Estrarre eventuali ticket di assistenza o registrazioni vocali associate.',
                'is_mandatory' => 0,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 44,
                'instruction' => 'Oscurare i nominativi o i dati sensibili di terze persone presenti nei documenti.',
                'is_mandatory' => 1,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Items per Task 3
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 45,
                'instruction' => 'Generare archivio .ZIP protetto da password.',
                'is_mandatory' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 45,
                'instruction' => 'Inviare la password tramite canale separato (es. SMS se file inviato via email).',
                'is_mandatory' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 'acdb1ad4-a999-40ab-9cb7-a6b8f57d2208',
                'checklist_id' => 45,
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
