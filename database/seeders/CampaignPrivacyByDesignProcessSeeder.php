<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\PrivacyDataType;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Support\Facades\DB;

class CampaignPrivacyByDesignProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Recupero entità base
            $companyId = Company::first()?->id;
            if (!$companyId) {
                $this->command->error('Nessuna company trovata.');
                return;
            }

            // Funzioni aziendali
            $marketingFunc = BusinessFunction::where('code', 'SUP-PLAN')->where('company_id', $companyId)->first();
            $itFunc = BusinessFunction::where('code', 'SUP-IT')->where('company_id', $companyId)->first();
            $dpoFunc = BusinessFunction::where('code', 'CTRL-DPO')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('code', 'CTRL-COMPL')->where('company_id', $companyId)->first();

            // Privacy Data
            $privacyDataTypeId = 1; // ID_BASE
            $privacyLegalBaseId = 1; // Consenso

            // 2. Creazione Processo
            $process = Process::updateOrCreate(
                ['name' => 'Procedura Privacy by Design per Nuove Campagne (Art. 25 GDPR)', 'company_id' => $companyId],
                [
                    'description' => 'Iter obbligatorio di valutazione e approvazione privacy da eseguire prima del go-live di qualsiasi nuova campagna di Lead Generation (es. Landing Page, Meta Ads, TikTok Ads).',
                    'business_function_id' => $marketingFunc->id ?? null,
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Analisi Finalità e Minimizzazione Dati
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'company_id' => $companyId,
                    'name' => 'Analisi Finalità e Minimizzazione Dati',
                    'business_function_id' => $marketingFunc->id ?? null,
                    'description' => 'Definizione della struttura dati strettamente necessaria da richiedere all\'utente nel form.',
                ]
            );

            $this->seedChecklist($task1, 'Assessment Minimizzazione', [
                'Definire l\'esatta provenienza dei dati (URL Landing Page o Piattaforma Social).',
                'Applicare il principio di minimizzazione: rimuovere dal form campi non strettamente necessari (es. data di nascita o indirizzo se non servono).',
            ]);

            // Mappatura Privacy Task 1
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task1->id, 'privacy_data_type_id' => $privacyDataTypeId],
                [
                    'privacy_legal_base_id' => $privacyLegalBaseId,
                    'access_level' => 'write',
                    'purpose' => 'Raccolta dati anagrafici per finalità di contatto commerciale su richiesta dell\'interessato.',
                    'is_encrypted' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 4. TASK 2: Redazione Informative e Consensi
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'company_id' => $companyId,
                    'name' => 'Redazione Informative e Consensi',
                    'business_function_id' => $marketingFunc->id ?? null,
                    'description' => 'Garantire trasparenza e libertà di scelta all\'utente tramite informative chiare.',
                ]
            );

            $this->seedChecklist($task2, 'Trasparenza e Granularità', [
                'Predisporre il link chiaro e visibile alla Privacy Policy specifica della campagna, aggiornata con l\'indicazione dei cessionari.',
                'Inserire Checkbox separate e NON pre-spuntate: una per Marketing Diretto e una per Cessione a Terzi.',
                'Garantire che non vi siano consensi condizionati (es. non forzare l\'utente ad accettare il marketing per scaricare un ebook).',
            ]);

            // 5. TASK 3: Requisiti di Tracciamento IT
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'company_id' => $companyId,
                    'name' => 'Requisiti di Tracciamento IT',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Garanzia dell\'integrità della prova del consenso acquisito.',
                ]
            );

            $this->seedChecklist($task3, 'Integrità del Dato', [
                'Testare che il webhook/API registri correttamente nel CRM il Timestamp esatto e l\'Indirizzo IP al momento del submit.',
                'Assicurarsi che l\'origine (source) e la versione della privacy policy accettata vengano loggati assieme al lead.',
            ]);

            // 6. TASK 4: Valutazione d\'Impatto (DPIA) e Sign-off
            $task4 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 4],
                [
                    'company_id' => $companyId,
                    'name' => 'Valutazione d\'Impatto (DPIA) e Sign-off',
                    'business_function_id' => $dpoFunc->id ?? null,
                    'description' => 'Validazione finale del rispetto dei requisiti privacy prima del go-live.',
                ]
            );

            $this->seedChecklist($task4, 'Approvazione Finale', [
                'Verificare se la campagna fa uso di profilazione automatizzata (se SI, richiede DPIA ex Art. 35 GDPR).',
                'Acquisire approvazione finale e sblocco da parte del DPO o dell\'Ufficio Legale.',
            ]);

            $this->command->info('Processo Privacy by Design popolato con successo!');
        });
    }

    private function seedChecklist(ProcessTask $task, string $name, array $items): void
    {
        $checklist = Checklist::updateOrCreate(
            ['process_task_id' => $task->id, 'name' => $name],
            ['sort_order' => 1]
        );

        foreach ($items as $index => $instruction) {
            ChecklistItem::updateOrCreate(
                ['checklist_id' => $checklist->id, 'instruction' => $instruction],
                [
                    'is_mandatory' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
