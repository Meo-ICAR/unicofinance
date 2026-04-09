<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ListSanitizationRPOProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Recupero entità base
            $company = Company::first();
            if (!$company) {
                $this->command->error('Nessuna company trovata.');
                return;
            }
            $companyId = $company->id;

            // Recupero funzione aziendale (IT o Data Management come richiesto)
            $itFunc = BusinessFunction::where('code', 'IT')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('code', 'SUP-IT')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('name', 'like', '%IT%')->where('company_id', $companyId)->first();

            // Costanti Privacy (ID_BASE = 1, Obbligo Legale = 3)
            $dataTypeBase = 1; 
            $legalBaseObbligo = 3;

            // 2. Creazione Processo Principale
            $process = Process::updateOrCreate(
                ['name' => 'Bonifica Liste Outbound e Controllo RPO', 'company_id' => $companyId],
                [
                    'description' => 'Iter obbligatorio di verifica provenienza, interrogazione del Registro Pubblico delle Opposizioni (RPO) e depurazione delle anagrafiche prima del caricamento sui sistemi di chiamata.',
                    'business_function_id' => $itFunc->id ?? null,
                    'target_model' => 'App\\Models\\LeadList',
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Verifica Provenienza e Due Diligence Fornitore
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1, 'company_id' => $companyId],
                [
                    'name' => 'Verifica Provenienza e Due Diligence Fornitore',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Audit preventivo sulla legittimità della lista acquisita o noleggiata.',
                ]
            );

            $this->seedChecklist($task1, 'Audit Fornitore Lista', [
                "Verificare che il contratto di acquisto/noleggio lista includa manleva per violazioni GDPR.",
                "Acquisire dal fornitore il campione (log) di prova dell'avvenuto consenso specifico per il telemarketing.",
            ], $companyId);

            // 4. TASK 2: Interrogazione RPO
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2, 'company_id' => $companyId],
                [
                    'name' => 'Interrogazione RPO',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Verifica delle numerazioni telefoniche nel Registro Pubblico delle Opposizioni (DPR 26/2022).',
                ]
            );

            $this->seedChecklist($task2, 'Connessione RPO', [
                "Generare l'hash delle numerazioni o preparare il tracciato secondo le specifiche del Ministero/Fondazione Ugo Bordoni.",
                "Salvare nel sistema il numero di ricevuta (Ticket RPO) che attesta l'avvenuta verifica in data odierna.",
            ], $companyId);

            // Mappatura Privacy Task 2
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => $dataTypeBase],
                [
                    'privacy_legal_base_id' => $legalBaseObbligo,
                    'access_level' => 'read',
                    'purpose' => 'Confronto crittografato delle numerazioni con il database del Registro Pubblico delle Opposizioni.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Depurazione (Sanitization) e Blacklist
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3, 'company_id' => $companyId],
                [
                    'name' => 'Depurazione (Sanitization) e Blacklist',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Pulizia definitiva della lista dai numeri non contattabili.',
                ]
            );

            $this->seedChecklist($task3, 'Bonifica Database', [
                "Eliminare dalla lista di carico tutte le numerazioni restituite come 'Iscritte' dall'RPO.",
                "Incrociare la lista rimanente con la Suppression List (Blacklist) interna aziendale ed eliminare i match.",
            ], $companyId);

            // Mappatura Privacy Task 3
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task3->id, 'privacy_data_type_id' => $dataTypeBase],
                [
                    'privacy_legal_base_id' => $legalBaseObbligo,
                    'access_level' => 'delete',
                    'purpose' => 'Rimozione fisica o inibizione permanente delle numerazioni iscritte all\'RPO o presenti in Blacklist interna.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 6. TASK 4: Sblocco e Timer di Scadenza
            $task4 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 4, 'company_id' => $companyId],
                [
                    'name' => 'Sblocco e Timer di Scadenza',
                    'business_function_id' => $itFunc->id ?? null, // Solitamente approvato da IT o Operations
                    'description' => 'Abilitazione al Dialer e impostazione della validità temporale della lista.',
                ]
            );

            $this->seedChecklist($task4, 'Autorizzazione al Caricamento', [
                "Sbloccare la lista depurata per l'importazione nel Dialer.",
                "Impostare un TTL (Time To Live) tassativo di 15 giorni per la lista: al 16° giorno il sistema deve bloccare in automatico le chiamate sui numeri residui.",
            ], $companyId);

            $this->command->info('Processo Bonifica Liste e Controllo RPO creato con successo!');
        });
    }

    /**
     * Helper per popolare checklist e relativi items.
     */
    private function seedChecklist(ProcessTask $task, string $name, array $items, $companyId): void
    {
        $checklist = Checklist::updateOrCreate(
            ['process_task_id' => $task->id, 'name' => $name, 'company_id' => $companyId],
            ['sort_order' => 1]
        );

        foreach ($items as $index => $instruction) {
            ChecklistItem::updateOrCreate(
                ['checklist_id' => $checklist->id, 'instruction' => $instruction, 'company_id' => $companyId],
                [
                    'is_mandatory' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
