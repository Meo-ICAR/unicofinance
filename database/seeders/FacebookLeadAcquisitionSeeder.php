<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\PrivacyDataType;
use App\Models\PrivacyLegalBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FacebookLeadAcquisitionSeeder extends Seeder
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

            // Recupero dinamico della funzione Marketing (Owner del processo Meta Ads)
            $mktgFunc = BusinessFunction::where('code', 'SUP-PLAN')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('name', 'like', '%Marketing%')->where('company_id', $companyId)->first();

            // Recupero ID Privacy
            $dataTypeBase = PrivacyDataType::where('slug', 'ID_BASE')->first()?->id ?? 1;
            $legalBaseConsenso = PrivacyLegalBase::where('name', 'Consenso')->first()?->id ?? 1;

            // 2. Creazione Processo Principale
            $process = Process::updateOrCreate(
                ['name' => 'Acquisizione Lead via Facebook/Meta Ads', 'company_id' => $companyId],
                [
                    'description' => 'Gestione del flusso di entrata lead tramite Facebook Instant Forms, inclusa la sincronizzazione API e la verifica di conformità dei consensi Meta-to-CRM.',
                    'business_function_id' => $mktgFunc->id ?? null,
                    'target_model' => 'App\\Models\\Lead',
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Validazione Configurazione Form Meta
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'name' => 'Validazione Configurazione Form Meta',
                    'company_id' => $companyId,
                    'business_function_id' => $mktgFunc->id ?? null,
                    'description' => 'Audit preventivo dei moduli istantanei Meta per garantire la conformità legale prima dell\'attivazione della campagna.',
                ]
            );

            $this->seedChecklist($task1, 'Audit Privacy Form Istantaneo', [
                'Verificare che il link alla Privacy Policy nel modulo Meta punti alla versione corretta di Confronta-facile.com.',
                'Confermare la presenza di disclaimer espliciti per la cessione dei dati a terzi installatori.',
            ]);

            // 4. TASK 2: Sincronizzazione API e Ingestion
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'name' => 'Sincronizzazione API e Ingestion',
                    'company_id' => $companyId,
                    'business_function_id' => $mktgFunc->id ?? null,
                    'description' => 'Ricezione dati tramite Webhook Meta e persistenza nel buffer di ingresso.',
                ]
            );

            $this->seedChecklist($task2, 'Integrità Tecnica Ingestion', [
                'Registrare il Facebook Lead ID univoco per evitare duplicati.',
                'Mappare i campi personalizzati del form Meta con le colonne del database interno.',
            ]);

            // Mappatura Privacy Task 2 (Write Access)
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => $dataTypeBase],
                [
                    'privacy_legal_base_id' => $legalBaseConsenso,
                    'access_level' => 'write',
                    'purpose' => 'Ricezione dati tramite Webhook Meta e persistenza nel buffer di ingresso.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Normalizzazione e Privacy Check
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'name' => 'Normalizzazione e Privacy Check',
                    'company_id' => $companyId,
                    'business_function_id' => $mktgFunc->id ?? null,
                    'description' => 'Conversione formati Meta in standard interni e verifica coerenza geografica dell\'IP.',
                ]
            );

            $this->seedChecklist($task3, 'Compliance Mapping', [
                'Trasformare i consensi \'Yes/No\' di Meta in flag booleani conformi allo schema interno.',
                'Verificare se l\'indirizzo IP fornito da Meta (se disponibile) è coerente con il mercato di riferimento.',
            ]);

            // 6. TASK 4: Attivazione Funnel Qualifica
            $task4 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 4],
                [
                    'name' => 'Attivazione Funnel Qualifica',
                    'company_id' => $companyId,
                    'business_function_id' => $mktgFunc->id ?? null,
                    'description' => 'Innesco del flusso di vendita e notifica agli operatori.',
                ]
            );

            $this->seedChecklist($task4, 'Passaggio alle Operations', [
                'Assegnare il lead al processo \'Acquisizione e Pre-Qualifica Lead (Confronta-facile.com)\'.',
                'Notificare il team di vendita per il primo contatto telefonico (Task Outbound).',
            ]);

            $this->command->info('Processo Facebook Lead Acquisition popolato con successo!');
        });
    }

    /**
     * Helper per popolare checklist e relativi items.
     */
    private function seedChecklist(ProcessTask $task, string $name, array $items): void
    {
        $checklist = Checklist::updateOrCreate(
            ['process_task_id' => $task->id, 'name' => $name, 'company_id' => $task->company_id],
            ['sort_order' => 1]
        );

        foreach ($items as $index => $instruction) {
            ChecklistItem::updateOrCreate(
                ['checklist_id' => $checklist->id, 'instruction' => $instruction, 'company_id' => $task->company_id],
                [
                    'is_mandatory' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
