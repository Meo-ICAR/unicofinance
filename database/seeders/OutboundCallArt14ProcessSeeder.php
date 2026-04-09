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

class OutboundCallArt14ProcessSeeder extends Seeder
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

            // Recupero o creazione funzione aziendale (Teleselling / Operations)
            $telesellingFunc = BusinessFunction::firstOrCreate(
                ['code' => 'BUS-TELE', 'company_id' => $companyId],
                [
                    'name' => 'Teleselling / Operations',
                    'macro_area' => \App\Enums\MacroArea::BUSINESS_COMMERCIALE,
                    'type' => \App\Enums\BusinessFunctionType::OPERATIVA,
                    'mission' => 'Esecuzione delle attività di contatto telefonico e vendita nel rispetto del GDPR.',
                ]
            );

            // Costanti Privacy (ID_BASE = 1, Obbligo Legale = 3)
            $dataTypeBase = 1;
            $legalBaseObbligo = 3;

            // 2. Creazione Processo Principale
            $process = Process::updateOrCreate(
                ['name' => 'Esecuzione Chiamata Outbound e Informativa Breve (Art. 14 GDPR)', 'company_id' => $companyId],
                [
                    'description' => "Script guidato a schermo per l'operatore, volto a fornire l'informativa breve all'interessato al momento del primo contatto e gestire immediatamente l'eventuale opposizione.",
                    'business_function_id' => $telesellingFunc->id,
                    'target_model' => 'App\\Models\\CallLog',
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Presentazione e Dichiarazione del Titolare
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1, 'company_id' => $companyId],
                [
                    'name' => 'Presentazione e Dichiarazione del Titolare',
                    'business_function_id' => $telesellingFunc->id,
                    'description' => "Identificazione certa dell'operatore e del Titolare del trattamento.",
                ]
            );

            $this->seedChecklist($task1, 'Identificazione Iniziale', [
                "Dichiarare il nome dell'operatore e per conto di quale azienda (Utility/Titolare) si sta chiamando.",
                "Avvisare l'utente se la chiamata è registrata per fini di qualità o contrattuali.",
            ], $companyId);

            // 4. TASK 2: Informativa Breve e Origine dei Dati - Art. 14
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2, 'company_id' => $companyId],
                [
                    'name' => 'Informativa Breve e Origine dei Dati - Art. 14',
                    'business_function_id' => $telesellingFunc->id,
                    'description' => "Adempimento degli obblighi di trasparenza previsti dall'Art. 14 GDPR.",
                ]
            );

            $this->seedChecklist($task2, 'Trasparenza Art. 14', [
                "Comunicare all'utente DA DOVE è stato acquisito il suo numero (es. 'Il suo numero è stato estratto dagli elenchi di [Nome Fornitore Lista]').",
                "Informare l'utente della finalità della chiamata (Proposta commerciale).",
                "Chiedere esplicitamente all'utente se desidera proseguire la conversazione.",
            ], $companyId);

            // Mappatura Privacy Task 2
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => $dataTypeBase],
                [
                    'privacy_legal_base_id' => $legalBaseObbligo,
                    'access_level' => 'read',
                    'purpose' => "Informare l'utente sull'origine dei suoi dati personali e sulla finalità commerciale della chiamata.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Triage Esito Privacy
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3, 'company_id' => $companyId],
                [
                    'name' => 'Triage Esito Privacy',
                    'business_function_id' => $telesellingFunc->id,
                    'description' => "Gestione delle diverse risposte dell'interessato in ambito privacy.",
                ]
            );

            $this->seedChecklist($task3, 'Gestione Risposta Utente', [
                "Se l'utente accetta: procedere con lo script di vendita (Passaggio a Task Commerciale).",
                "Se l'utente si oppone/riaggancia: chiudere la chiamata registrando l'esito come 'Non Interessato' o 'Inutile Riprovare'.",
                "Se l'utente richiede la cancellazione: innescare immediatamente la procedura 'Gestione Immediata Opt-Out / Blacklist'.",
            ], $companyId);

            $this->command->info('Processo Chiamata Outbound Art. 14 popolato con successo!');
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
