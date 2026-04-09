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

class LeadTransferProcessSeeder extends Seeder
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
            $legalFunc = BusinessFunction::where('code', 'SUP-LEG-AMM')->where('company_id', $companyId)->first() 
                ?? BusinessFunction::where('code', 'CTRL-COMPL')->where('company_id', $companyId)->first();
            
            $itFunc = BusinessFunction::where('code', 'SUP-IT')->where('company_id', $companyId)->first();
            
            $marketingFunc = BusinessFunction::where('code', 'SUP-PLAN')->where('company_id', $companyId)->first();

            // Privacy Data
            $privacyDataTypeId = PrivacyDataType::where('slug', 'ID_BASE')->value('id');
            $privacyLegalBaseId = DB::table('privacy_legal_bases')->where('name', 'Consenso')->value('id');

            // 2. Creazione Processo
            $process = Process::updateOrCreate(
                ['name' => 'Procedura di Cessione Lead a Call Center Esterni', 'company_id' => $companyId],
                [
                    'description' => 'Iter obbligatorio per la qualifica del partner B2B, la firma del contratto di manleva e la trasmissione sicura delle liste anagrafiche.',
                    'business_function_id' => $legalFunc->id ?? null,
                    'is_active' => true,
                ]
            );

            // 3. TASK 1: Due Diligence e Qualifica Cliente
            $task1 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 1],
                [
                    'company_id' => $companyId,
                    'name' => 'Due Diligence e Qualifica Cliente',
                    'business_function_id' => $legalFunc->id ?? null,
                    'description' => 'Verifiche documentali sull\'affidabilità del partner esterno.',
                ]
            );

            $this->seedChecklist($task1, 'Verifiche Preliminari Compliance', [
                'Richiesta e verifica iscrizione al ROC (Registro Operatori Comunicazione).',
                'Acquisizione contatti del DPO del call center o del referente privacy.',
            ]);

            // 4. TASK 2: Contrattualizzazione
            $task2 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 2],
                [
                    'company_id' => $companyId,
                    'name' => 'Contrattualizzazione',
                    'business_function_id' => $legalFunc->id ?? null,
                    'description' => 'Negoziazione e firma degli accordi legali e di manleva.',
                ]
            );

            $this->seedChecklist($task2, 'Firma Accordo di Cessione', [
                'Verifica presenza clausola di limitazione finalità (solo settore fotovoltaico).',
                'Verifica divieto esplicito di sub-cessione delle liste a terzi.',
                'Firma della manleva di responsabilità sui contatti telefonici.',
            ]);

            // 5. TASK 3: Trasmissione Sicura Liste
            $task3 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 3],
                [
                    'company_id' => $companyId,
                    'name' => 'Trasmissione Sicura Liste',
                    'business_function_id' => $itFunc->id ?? null,
                    'description' => 'Operazioni tecniche di export e invio protetto dei dati.',
                ]
            );

            $this->seedChecklist($task3, 'Regole di Trasmissione IT', [
                'Divieto assoluto di invio liste in chiaro via email.',
                'Utilizzo API protette (HTTPS) o file .csv crittografati con password trasmessa su canale separato.',
            ]);

            // Mappatura Privacy Task 3
            if ($privacyDataTypeId && $privacyLegalBaseId) {
                DB::table('process_task_privacy_data')->updateOrInsert(
                    ['process_task_id' => $task3->id, 'privacy_data_type_id' => $privacyDataTypeId],
                    [
                        'privacy_legal_base_id' => $privacyLegalBaseId,
                        'access_level' => 'read',
                        'is_encrypted' => 1,
                        'is_shared_externally' => 1,
                        'purpose' => 'Estrazione e invio crittografato della lista contatti qualificati al call center cessionario.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 6. TASK 4: Flusso di Ritorno e Aggiornamento Blacklist
            $task4 = ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'sequence_number' => 4],
                [
                    'company_id' => $companyId,
                    'name' => 'Flusso di Ritorno e Aggiornamento Blacklist',
                    'business_function_id' => $marketingFunc->id ?? $validClients->random()->id ?? null,
                    'description' => 'Monitoraggio feedback post-cession e recepimento istanze di opt-out.',
                ]
            );

            $this->seedChecklist($task4, 'Gestione Opt-Out e Qualità Dati', [
                'Ricezione report mensile dei bounce e delle richieste di cancellazione (Opt-out) dal call center.',
                'Inserimento massivo delle numerazioni revocate nella Suppression List (Blacklist) aziendale centrale.',
            ]);

            $this->command->info('Processo di Cessione Lead popolato con successo!');
        });
    }

    /**
     * Helper per seminare checklist e relativi item
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
