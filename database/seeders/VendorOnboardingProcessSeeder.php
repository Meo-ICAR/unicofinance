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

class VendorOnboardingProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 1. Recupero entità base
            $companyId = Company::first()?->id;
            if (!$companyId) {
                $this->command->error('Nessuna company trovata.');
                return;
            }

            // Funzioni aziendali
            $itFunc = BusinessFunction::where('code', 'SUP-IT')->where('company_id', $companyId)->first();
            $legalFunc = BusinessFunction::whereIn('code', ['CTRL-COMPL', 'SUP-LEG-AMM'])->where('company_id', $companyId)->first();
            $dpoFunc = BusinessFunction::where('code', 'CTRL-DPO')->where('company_id', $companyId)->first() 
                ?? BusinessFunction::where('code', 'CTRL-COMPL')->where('company_id', $companyId)->first();

            // Privacy Data
            $privacyDataTypeId = 1; // ID_BASE
            $privacyLegalBaseId = 3; // Obbligo Legale (Art 28)

            // 2. Creazione Processo
            $process = Process::updateOrCreate(
                ['name' => 'Onboarding Fornitore e Nomina Responsabile Esterno (Art. 28 GDPR)', 'company_id' => $companyId],
                [
                    'description' => 'Iter obbligatorio per la due diligence, la stipula del DPA (Data Processing Agreement) e l\'inserimento nel Registro dei Responsabili Esterni prima di affidare dati personali a terzi.',
                    'business_function_id' => $legalFunc->id ?? null,
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Vendor Security Assessment
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'company_id' => $companyId,
                    'name' => 'Vendor Security Assessment',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Valutazione tecnica dell\'affidabilità dei sistemi e dell\'ubicazione del dato.',
                ]
            );

            $this->seedChecklist($task1, 'Due Diligence e Valutazione Sicurezza', [
                'Richiedere e verificare le certificazioni di sicurezza del fornitore (es. ISO 27001, SOC2).',
                'Verificare l\'ubicazione dei Data Center: i dati restano in UE o c\'è un trasferimento Extra-UE?',
                'Se Extra-UE, verificare la presenza di Clausole Contrattuali Tipo (SCC) o decisioni di adeguatezza.',
            ]);

            // 4. TASK 2: Negoziazione e Stesura DPA
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'company_id' => $companyId,
                    'name' => 'Negoziazione e Stesura DPA',
                    'business_function_id' => $legalFunc->id ?? null,
                    'description' => 'Formalizzazione dei vincoli legali e delle istruzioni di trattamento.',
                ]
            );

            $this->seedChecklist($task2, 'Clausole Obbligatorie Art. 28', [
                'Inserire clausola sui tempi di notifica in caso di Data Breach da parte del fornitore (es. entro 48 ore).',
                'Inserire l\'obbligo di richiedere autorizzazione scritta per l\'utilizzo di eventuali Sub-Responsabili.',
                'Inserire clausola sul diritto di Audit da parte della nostra azienda.',
            ]);

            // Mappatura Privacy Task 2
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => $privacyDataTypeId],
                [
                    'privacy_legal_base_id' => $privacyLegalBaseId,
                    'access_level' => 'write',
                    'purpose' => 'Definizione formale delle istruzioni, della natura e della durata del trattamento affidato al fornitore.',
                    'is_encrypted' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Firma e Aggiornamento Registro Trattamenti
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'company_id' => $companyId,
                    'name' => 'Firma e Aggiornamento Registro Trattamenti',
                    'business_function_id' => $dpoFunc->id ?? null,
                    'description' => 'Firma del contratto e censimento nel registro responsabili esterni.',
                ]
            );

            $this->seedChecklist($task3, 'Chiusura Iter e Registrazione', [
                'Acquisizione del contratto principale e del DPA controfirmati digitalmente.',
                'Inserimento formale del fornitore nell\'Allegato \'Elenco dei Responsabili Esterni\' del nostro Registro Trattamenti.',
                'Impostare un reminder annuale per la revisione della qualifica del fornitore.',
            ]);

            DB::commit();
            $this->command->info('Processo di Onboarding Fornitore (Art. 28) popolato con successo!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Errore durante il seeding: ' . $e->getMessage());
        }
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
