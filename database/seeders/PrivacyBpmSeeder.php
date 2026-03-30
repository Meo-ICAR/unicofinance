<?php

namespace Database\Seeders;

use App\Enums\BusinessFunctionType;
use App\Enums\MacroArea;
use App\Enums\OutsourcableStatus;
use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\PrivacyDataType;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrivacyBpmSeeder extends Seeder
{
    // In PrivacyBpmSeeder.php
    public function run(): void
    {
        $companyId = Company::first()->id;  // Usa un ID esistente o crealo

        // Find or create the DPO and Legal functions
        $dpoFunction = BusinessFunction::where('code', 'DPO')->first();
        $legalFunction = BusinessFunction::where('code', 'LEGAL')->first();

        if (!$dpoFunction || !$legalFunction) {
            $this->command->warn('DPO or Legal business functions not found. Skipping PrivacyBpmSeeder.');
            return;
        }

        // Find an existing process or create a sample one
        $process = Process::first();
        if (!$process) {
            $this->command->warn('No processes found. Skipping PrivacyBpmSeeder.');
            return;
        }

        // Supponiamo di avere un task: "Redazione DPIA (Valutazione Impatto)"
        $dpiaTask = ProcessTask::create([
            'process_id' => $process->id,
            'name' => 'Redazione Documento DPIA',
            'business_function_id' => $legalFunction->id,  // Funzione principale
            'sequence_number' => 1,
        ]);

        // Definiamo la matrice RACI per questo task
        $roles = [
            ['role' => 'R', 'func' => 'LEGAL'],  // Chi scrive materialmente
            ['role' => 'A', 'func' => 'DPO'],  // Chi firma e si prende la responsabilità
            ['role' => 'C', 'func' => 'IT'],  // Chi deve fornire i dettagli tecnici
            ['role' => 'I', 'func' => 'CEO'],  // Chi viene informato a fine processo
        ];

        foreach ($roles as $r) {
            $function = BusinessFunction::where('code', $r['func'])->first();

            if ($function) {
                RaciAssignment::create([
                    'company_id' => $companyId,
                    'process_task_id' => $dpiaTask->id,
                    'business_function_id' => $function->id,
                    'role' => $r['role'],
                ]);
            }
        }

        // All'interno di un altro Seeder (es. PrivacyBpmSeeder)
        $task = ProcessTask::where('name', 'Verifica Stato Salute per Polizza')->first();

        if ($task) {
            // Recupera gli ID dal dizionario appena creato
            $healthDataId = PrivacyDataType::where('slug', 'HEALTH_DATA')->value('id');
            $idBaseId = PrivacyDataType::where('slug', 'ID_BASE')->value('id');

            // Collega i dati al task nella tabella pivot
            $task->privacyDataTypes()->sync([$healthDataId, $idBaseId]);
        }
    }
}
