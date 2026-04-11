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

class VocalOrderProcessSeeder extends Seeder
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
            // 1. Crea il Processo Principale
            // -------------------------------------------------------
            $process = Process::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => 'Acquisizione Contratto tramite Vocal Order (Utility)',
                ],
                [
                    'description' => "Procedura guidata per la stipula del contratto telefonico (Luce/Gas/Telco). Include lo script legale, la registrazione audio e l'archiviazione sicura del file.",
                    'target_model' => 'App\Models\Contract',
                    'is_active' => true,
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            // -------------------------------------------------------
            // 2. Task 1 – Consenso alla Registrazione e Identificazione
            // -------------------------------------------------------
            $task1 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 1,
                ],
                [
                    'name' => 'Consenso alla Registrazione e Identificazione',
                    'description' => "Identificazione certa dell'intestatario e acquisizione del consenso alla registrazione della chiamata.",
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task1,
                'Fase di Apertura Vocal Order',
                [
                    "Chiedere conferma del Nome, Cognome e Codice Fiscale dell'intestatario.",
                    'Enunciare chiaramente: "Questa chiamata viene registrata per la conclusione del contratto, è d\'accordo?".',
                    'Verificare che l\'utente risponda con un "SÌ" udibile e chiaro.',
                ]
            );

            // Mappatura Privacy per Task 1
            $this->seedPrivacyData(
                $task1->id,
                privacyDataTypeId: 1,  // ID_BASE
                privacyLegalBaseId: 2,  // Esecuzione Contratto (Art. 6 par. 1 lett. b)
                accessLevel: 'write',
                purpose: "Identificazione certa dell'intestatario e acquisizione del consenso alla registrazione della chiamata.",
                isEncrypted: true,
                isSharedExternally: false
            );

            // -------------------------------------------------------
            // 3. Task 2 – Lettura Proposta Economica e Oneri
            // -------------------------------------------------------
            $task2 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 2,
                ],
                [
                    'name' => 'Lettura Proposta Economica e Oneri',
                    'description' => "Lettura obbligatoria dell'offerta commerciale, dei costi di attivazione e delle condizioni contrattuali.",
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task2,
                'Script Economico Obbligatorio',
                [
                    "Leggere il nome dell'offerta commerciale e il prezzo della materia prima.",
                    'Specificare la durata del contratto e la frequenza della fatturazione.',
                    "Enunciare chiaramente i costi di attivazione o l'assenza di essi.",
                ]
            );

            // -------------------------------------------------------
            // 4. Task 3 – Manifestazione del Consenso e Recesso
            // -------------------------------------------------------
            $task3 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 3,
                ],
                [
                    'name' => 'Manifestazione del Consenso e Recesso',
                    'description' => 'Informazione sul diritto di ripensamento e acquisizione del consenso finale alla sottoscrizione.',
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task3,
                'Clausole Legali Finali',
                [
                    "Informare l'utente sul Diritto di Ripensamento (14 giorni) e sulle modalità di esercizio.",
                    'Acquisire il consenso finale alla sottoscrizione: "Conferma di voler sottoscrivere il contratto X con l\'azienda Y?".',
                ]
            );

            // -------------------------------------------------------
            // 5. Task 4 – Chiusura e Archiviazione File
            // -------------------------------------------------------
            $task4 = ProcessTask::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'sequence_number' => 4,
                ],
                [
                    'name' => 'Chiusura e Archiviazione File',
                    'description' => "Interruzione della registrazione, verifica dell'integrità del file audio e invio della sintesi contrattuale.",
                    'business_function_id' => $defaultBusinessFunction->id,
                ]
            );

            $this->seedChecklist(
                $task4,
                'Integrità del Dato Audio',
                [
                    'Interrompere la registrazione e verificare che il file audio sia stato generato correttamente.',
                    "Associare il file audio (o l'URL sicuro) al record del contratto nel CRM.",
                    "Inviare automaticamente la sintesi contrattuale via email/SMS all'utente.",
                ]
            );

            // Mappatura Privacy per Task 4
            $this->seedPrivacyData(
                $task4->id,
                privacyDataTypeId: 1,  // ID_BASE
                privacyLegalBaseId: 2,  // Esecuzione Contratto (Art. 6 par. 1 lett. b)
                accessLevel: 'write',
                purpose: 'Archiviazione sicura del file audio di registrazione contrattuale e associazione al record del contratto.',
                isEncrypted: true,
                isSharedExternally: false
            );

            $this->command->info('✅ VocalOrderProcessSeeder completato: processo "Acquisizione Contratto tramite Vocal Order (Utility)" creato con successo.');
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
