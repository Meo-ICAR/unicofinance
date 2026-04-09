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

class LeadAcquisitionProcessSeeder extends Seeder
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

            // Recupero dinamico delle funzioni aziendali (Marketing e Operations/Back Office)
            $mktgFunc = BusinessFunction::where('code', 'SUP-PLAN')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('name', 'like', '%Marketing%')->where('company_id', $companyId)->first();
            
            $opsFunc = BusinessFunction::where('code', 'BUS-BO')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('name', 'like', '%Back Office%')->where('company_id', $companyId)->first()
                ?? BusinessFunction::where('name', 'like', '%Operazioni%')->where('company_id', $companyId)->first();

            // Recupero ID Privacy (usando slug o nome come fallback)
            $dataTypeBase = PrivacyDataType::where('slug', 'ID_BASE')->first()?->id ?? 1;
            $legalBaseConsenso = PrivacyLegalBase::where('name', 'Consenso')->first()?->id ?? 1;

            // 2. Creazione Processo Principale
            $process = Process::updateOrCreate(
                ['name' => 'Acquisizione e Pre-Qualifica Lead (Confronta-facile.com)', 'company_id' => $companyId],
                [
                    'description' => 'Iter operativo per l\'ingresso di un nuovo lead dal portale web, la validazione dei consensi privacy e la pre-qualifica telefonica prima della cessione al partner installatore.',
                    'business_function_id' => $mktgFunc->id ?? null,
                    'target_model' => 'App\\Models\\Lead',
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Ingestion Webhook e Validazione Consensi
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'name' => 'Ingestion Webhook e Validazione Consensi',
                    'company_id' => $companyId,
                    'business_function_id' => $mktgFunc->id ?? null,
                    'description' => 'Ricezione automatizzata del lead dal webhook e controllo di conformità dei flag privacy.',
                ]
            );

            $this->seedChecklist($task1, 'Controllo Integrità Consenso Web', [
                'Verificare la presenza del Timestamp esatto e dell\'Indirizzo IP dell\'utente al momento del submit.',
                'Verificare che il campo \'privacy_policy_version\' corrisponda alla versione attualmente in vigore sul portale.',
                'Confermare che i consensi per Marketing (Finalità B) e Cessione a Terzi (Finalità C) siano valorizzati esplicitamente (non nulli).',
            ]);

            // Mappatura Privacy Task 1
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task1->id, 'privacy_data_type_id' => $dataTypeBase],
                [
                    'privacy_legal_base_id' => $legalBaseConsenso,
                    'access_level' => 'write',
                    'purpose' => 'Raccolta automatizzata dell\'anagrafica e tracciamento inoppugnabile delle manifestazioni di volontà dell\'interessato.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 4. TASK 2: Arricchimento e Profilazione Tecnica
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'name' => 'Arricchimento e Profilazione Tecnica',
                    'company_id' => $companyId,
                    'business_function_id' => $mktgFunc->id ?? null,
                    'description' => 'Integrazione dei dati tecnici raccolti tramite quiz o funnel di vendita.',
                ]
            );

            $this->seedChecklist($task2, 'Completamento Dati Funnel', [
                'Associare le risposte del quiz tecnico al record anagrafico del Lead.',
                'Calcolare e assegnare lo \'Scoring\' preliminare di fattibilità fotovoltaico.',
            ]);

            // Mappatura Privacy Task 2
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => $dataTypeBase],
                [
                    'privacy_legal_base_id' => $legalBaseConsenso,
                    'access_level' => 'update',
                    'purpose' => 'Registrazione dei dati tecnici (es. esposizione tetto, consumi) per ottimizzare l\'offerta commerciale.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Pre-Qualifica Telefonica Outbound
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'name' => 'Pre-Qualifica Telefonica Outbound',
                    'company_id' => $companyId,
                    'business_function_id' => $opsFunc->id ?? $mktgFunc->id ?? null,
                    'description' => 'Verifica telefonica dei dati e conferma dell\'interesse prima del trasferimento al partner.',
                ]
            );

            $this->seedChecklist($task3, 'Script di Chiamata Operatore', [
                'Verificare che l\'utente non sia iscritto al Registro Pubblico delle Opposizioni (se applicabile alla campagna).',
                'Effettuare il primo tentativo di chiamata entro 15 minuti dall\'ingresso del lead.',
                'Durante la chiamata, ricordare all\'utente che i dati saranno trasmessi a un partner installatore per il preventivo (Trasparenza ex Art. 13 GDPR).',
            ]);

            $this->command->info('Processo Lead Acquisition popolato con successo!');
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
