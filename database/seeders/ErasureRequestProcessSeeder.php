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

class ErasureRequestProcessSeeder extends Seeder
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
            $privacyFunc = BusinessFunction::whereIn('code', ['CTRL-COMPL', 'SUP-LEG-AMM'])->where('company_id', $companyId)->first();
            $itFunc = BusinessFunction::where('code', 'SUP-IT')->where('company_id', $companyId)->first();

            // Privacy Data (ID 1 e ID 3 come richiesto)
            $privacyDataTypeId = 1; // ID_BASE - Dati Anagrafici
            $privacyLegalBaseId = 3; // Obbligo Legale

            // 2. Creazione Processo
            $process = Process::updateOrCreate(
                ['name' => 'Gestione Richiesta di Cancellazione (Art. 17 GDPR)', 'company_id' => $companyId],
                [
                    'description' => 'Iter procedurale per evadere le richieste di diritto all\'oblio degli interessati, garantendo l\'inserimento in Suppression List e la comunicazione ai cessionari.',
                    'business_function_id' => $privacyFunc->id ?? null,
                    'target_model' => 'App\Models\PrivacyRequestLog',
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Ricezione e Verifica Identità
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'company_id' => $companyId,
                    'name' => 'Ricezione e Verifica Identità',
                    'business_function_id' => $privacyFunc->id ?? null,
                    'description' => 'Ricerca anagrafica e validazione del mittente richiedente.',
                ]
            );

            $this->seedChecklist($task1, 'Identificazione Interessato', [
                'Ricerca dell\'utente nel CRM tramite indirizzo email o numero di telefono mittente.',
                'Divieto di richiedere copia del documento d\'identità se l\'email corrisponde a quella a sistema (Principio di minimizzazione).',
            ]);

            // 4. TASK 2: Inibizione e Suppression List
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'company_id' => $companyId,
                    'name' => 'Inibizione e Suppression List',
                    'business_function_id' => $itFunc->id ?? $privacyFunc->id ?? null,
                    'description' => 'Azione tecnica di pseudonimizzazione e blocco futuro dei contatti.',
                ]
            );

            $this->seedChecklist($task2, 'Azione Tecnica sui Dati', [
                'NON eliminare fisicamente l\'intero record dal database storico.',
                'Inserire email e telefono nella Suppression List (Blacklist) aziendale con flag DO_NOT_CONTACT = true.',
            ]);

            // Mappatura Privacy Task 2
            DB::table('process_task_privacy_data')->updateOrInsert(
                ['process_task_id' => $task2->id, 'privacy_data_type_id' => $privacyDataTypeId],
                [
                    'privacy_legal_base_id' => $privacyLegalBaseId,
                    'access_level' => 'update',
                    'purpose' => 'Pseudonimizzazione del record principale e inserimento dei dati minimi di contatto nella Blacklist per inibire futuri contatti.',
                    'is_encrypted' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 5. TASK 3: Comunicazione ai Cessionari - Art. 19 GDPR
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'company_id' => $companyId,
                    'name' => 'Comunicazione ai Cessionari - Art. 19 GDPR',
                    'business_function_id' => $privacyFunc->id ?? null,
                    'description' => 'Disseminazione dell\'obbligo di cancellazione ai partner B2B terzi.',
                ]
            );

            $this->seedChecklist($task3, 'Notifica Terze Parti', [
                'Verificare tramite i log di sistema a quali call center / clienti è stato venduto questo specifico lead.',
                'Inviare notifica formale ai cessionari richiedendo la cancellazione dai loro database (Art. 19 GDPR).',
            ]);

            // 6. TASK 4: Riscontro Finale all'Interessato
            $task4 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 4],
                [
                    'company_id' => $companyId,
                    'name' => 'Riscontro Finale all\'Interessato',
                    'business_function_id' => $privacyFunc->id ?? null,
                    'description' => 'Comunicazione di chiusura iter all\'interessato.',
                ]
            );

            $this->seedChecklist($task4, 'Chiusura Ticket', [
                'Inviare email di conferma di avvenuta cancellazione all\'utente, spiegando il funzionamento della Suppression List.',
                'Verificare che l\'intero iter sia stato concluso entro 30 giorni dalla ricezione (Art. 12 GDPR).',
            ]);

            DB::commit();
            $this->command->info('Processo di Cancellazione (Art. 17) popolato con successo!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Errore durante il seeding: ' . $e->getMessage());
        }
    }

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
