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

class OptOutManagementProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $companyId = Company::first()?->id;
            $defaultBusinessFunction = BusinessFunction::first();  // Get first business function as default

            if (!$companyId) {
                $this->command->error('Nessuna azienda trovata. Eseguire prima il CompanySeeder.');
                return;
            }

            if (!$defaultBusinessFunction) {
                $this->command->error('Nessuna business function trovata. Eseguire prima il BusinessFunctionSeeder.');
                return;
            }

            // -------------------------------------------------------
            // 1. Processo Principale
            // -------------------------------------------------------
            $process = Process::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => 'Gestione Immediata Opt-Out e Blacklist (Art. 21 GDPR)',
                ],
                [
                    'description' => "Procedura di inibizione immediata della numerazione a seguito di opposizione dell'interessato, con aggiornamento della Suppression List e comunicazione di ritorno al Committente.",
                    'target_model' => 'App\Models\Blacklist',
                    'is_active' => true,
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            // -------------------------------------------------------
            // 2. Task 1 – Interruzione e Registrazione Esito Assoluto
            // -------------------------------------------------------
            $task1 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 1,
                ],
                [
                    'name' => 'Interruzione e Registrazione Esito Assoluto',
                    'description' => "Blocco immediato della proposta commerciale e registrazione dell'esito come OPT-OUT.",
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task1,
                'Triage Opposizione',
                [
                    'Interrompere immediatamente qualsiasi proposta commerciale (Diritto di Opposizione Assoluto).',
                    "Registrare l'esito della chiamata TASSATIVAMENTE come 'OPT-OUT / RICHIESTA CANCELLAZIONE' (e NON come 'Non Interessato').",
                ]
            );

            // -------------------------------------------------------
            // 3. Task 2 – Inserimento in Suppression List Locale
            // -------------------------------------------------------
            $task2 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 2,
                ],
                [
                    'name' => 'Inserimento in Suppression List Locale',
                    'description' => 'Pseudonimizzazione del record e inserimento nella Blacklist di blocco del Dialer.',
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task2,
                'Azione Tecnica Dialer',
                [
                    'Sganciare istantaneamente la numerazione dalla coda di chiamata attiva e da eventuali ricontatti programmati.',
                    "Inserire l'hash del numero nella Suppression List globale dell'azienda (valida per tutte le campagne future).",
                ]
            );

            // Mappatura Privacy per Task 2
            // ID 3 = Obbligo Legale (Art. 6 par. 1 lett. c): conserviamo il numero in blacklist
            // perché la legge impone di ricordare di NON chiamarlo più.
            $this->seedPrivacyData(
                $task2->id,
                privacyDataTypeId: 1,  // ID_BASE – Numero di Telefono
                privacyLegalBaseId: 3,  // Obbligo Legale (Art. 6 par. 1 lett. c)
                accessLevel: 'update',
                purpose: 'Pseudonimizzazione del record e inserimento nella Blacklist di blocco del Dialer.',
                isEncrypted: true,
                isSharedExternally: false
            );

            // -------------------------------------------------------
            // 4. Task 3 – Notifica al Titolare / Utility
            // -------------------------------------------------------
            $task3 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 3,
                ],
                [
                    'name' => 'Notifica al Titolare / Utility',
                    'description' => 'Sincronizzazione con il Committente tramite log temporale ed export SFTP di fine giornata.',
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task3,
                'Sincronizzazione Committente',
                [
                    'Tracciare il log temporale della richiesta di Opt-Out.',
                    'Inserire il record nel flusso di esportazione (SFTP) di fine giornata per notificare la Utility/Committente della revoca, affinché aggiorni i propri sistemi.',
                ]
            );

            $this->command->info('✅ OptOutManagementProcessSeeder completato: processo "Gestione Immediata Opt-Out e Blacklist (Art. 21 GDPR)" creato con successo.');
        });
    }

    /**
     * Crea o aggiorna una checklist con i suoi item per un dato task.
     */
    private function seedChecklist(ProcessTask $task, string $name, array $items): void
    {
        $checklist = Checklist::updateOrCreate(
            [
                'process_task_id' => $task->id,
                'name' => $name,
                'company_id' => $task->company_id,
            ],
            [
                'sort_order' => 1,
            ]
        );

        foreach ($items as $index => $instruction) {
            ChecklistItem::updateOrCreate(
                [
                    'checklist_id' => $checklist->id,
                    'instruction' => $instruction,
                    'company_id' => $task->company_id,
                ],
                [
                    'is_mandatory' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    /**
     * Inserisce o aggiorna un record nella pivot process_task_privacy_data.
     */
    private function seedPrivacyData(
        int $processTaskId,
        int $privacyDataTypeId,
        int $privacyLegalBaseId,
        string $accessLevel,
        string $purpose,
        bool $isEncrypted,
        bool $isSharedExternally
    ): void {
        DB::table('process_task_privacy_data')->updateOrInsert(
            [
                'process_task_id' => $processTaskId,
                'privacy_data_type_id' => $privacyDataTypeId,
            ],
            [
                'privacy_legal_base_id' => $privacyLegalBaseId,
                'access_level' => $accessLevel,
                'purpose' => $purpose,
                'is_encrypted' => $isEncrypted ? 1 : 0,
                'is_shared_externally' => $isSharedExternally ? 1 : 0,
            ]
        );
    }
}
