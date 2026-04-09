<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Support\Facades\DB;

class DataBreachProcessSeeder extends Seeder
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
            $itFunc = BusinessFunction::where('code', 'SUP-IT')->where('company_id', $companyId)->first();
            $dpoFunc = BusinessFunction::where('code', 'CTRL-DPO')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('code', 'CTRL-COMPL')->where('company_id', $companyId)->first();
            $legalFunc = BusinessFunction::whereIn('code', ['SUP-LEG-AMM', 'CTRL-COMPL'])->where('company_id', $companyId)->first();

            // 2. Creazione Processo
            $process = Process::updateOrCreate(
                ['name' => 'Procedura di Gestione e Notifica Data Breach', 'company_id' => $companyId],
                [
                    'description' => 'Flusso d\'emergenza per la rilevazione, classificazione e notifica delle violazioni di dati personali entro le 72 ore (Art. 33) e comunicazione agli interessati (Art. 34).',
                    'business_function_id' => $dpoFunc->id ?? null,
                    'target_model' => 'App\Models\DataBreachLog',
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Rilevazione e Triage Iniziale
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'company_id' => $companyId,
                    'name' => 'Rilevazione e Triage Iniziale',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Isolamento dell\'incidente e marcatura temporale della scoperta.',
                ]
            );

            $this->seedChecklist($task1, 'Identificazione Incidente', [
                'Annotare data e ora esatta della scoperta e dell\'accadimento.',
                'Descrivere la natura della violazione (es. perdita di disponibilità, riservatezza o integrità).',
                'Isolare i sistemi coinvolti per impedire la propagazione della violazione.',
            ]);

            // 4. TASK 2: Valutazione del Rischio
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'company_id' => $companyId,
                    'name' => 'Valutazione del Rischio',
                    'business_function_id' => $dpoFunc->id ?? null,
                    'description' => 'Analisi della gravità dell\'evento per i diritti e le libertà delle persone fisiche.',
                ]
            );

            $this->seedChecklist($task2, 'Assessment Gravità', [
                'Determinare se sono coinvolti dati particolari (es. sanitari, giudiziari).',
                'Valutare la probabilità di impatto negativo sui diritti degli interessati (es. furto d\'identità, danno reputazionale).',
                'Decisione: Il rischio è superiore alla soglia di notifica al Garante?',
            ]);

            // Mappatura Privacy Task 2
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => 1], // ID_BASE come proxy per l'analisi
                [
                    'privacy_legal_base_id' => 3, // Obbligo Legale
                    'access_level' => 'read',
                    'purpose' => 'Analisi della gravità della violazione in base alla tipologia di dati e al numero di interessati coinvolti.',
                    'is_encrypted' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Notifica all\'Autorità Garante (Art. 33)
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'company_id' => $companyId,
                    'name' => 'Notifica all\'Autorità Garante (Art. 33)',
                    'business_function_id' => $legalFunc->id ?? null,
                    'description' => 'Invio dei dati della violazione al Garante Privacy (72 ore).',
                ]
            );

            $this->seedChecklist($task3, 'Adempimento Art. 33', [
                'Inviare notifica telematica al Garante entro 72 ore dalla scoperta.',
                'Specificare le misure tecniche adottate o proposte per attenuare i danni.',
            ]);

            // 6. TASK 4: Comunicazione agli Interessati (Art. 34)
            $task4 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 4],
                [
                    'company_id' => $companyId,
                    'name' => 'Comunicazione agli Interessati (Art. 34)',
                    'business_function_id' => $legalFunc->id ?? null,
                    'description' => 'Avviso diretto alle persone colpite in caso di rischio elevato.',
                ]
            );

            $this->seedChecklist($task4, 'Comunicazione Art. 34', [
                'Se il rischio è elevato, informare i singoli interessati con linguaggio semplice e chiaro.',
                'Indicare il nome e i contatti del DPO per ricevere informazioni.',
            ]);

            // 7. TASK 5: Chiusura e Registro Violazioni
            $task5 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 5],
                [
                    'company_id' => $companyId,
                    'name' => 'Chiusura e Registro Violazioni',
                    'business_function_id' => $dpoFunc->id ?? null,
                    'description' => 'Finalizzazione della documentazione interna e analisi post-evento.',
                ]
            );

            $this->seedChecklist($task5, 'Documentazione Post-Incidente', [
                'Aggiornare il Registro interno delle violazioni (obbligatorio anche se non si è fatta la notifica).',
                'Analisi post-mortem: quali misure di sicurezza implementare per evitare il ripetersi del fatto.',
            ]);

            $this->command->info('Processo Data Breach popolato con successo!');
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
