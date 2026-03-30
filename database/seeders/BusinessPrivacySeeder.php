<?php

namespace Database\Seeders;

use App\Enums\BusinessFunctionType;
use App\Enums\MacroArea;
use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use Illuminate\Database\Seeder;

class BusinessPrivacySeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::first()->id;  // Usa un ID esistente o crealo

        // 1. Creazione Funzione Business Responsabile
        $dpoFunction = BusinessFunction::updateOrCreate([
            'code' => 'DPO_OFFICE',
            'company_id' => $companyId,
        ], [
            'name' => 'Ufficio Protezione Dati (DPO)',
            'macro_area' => MacroArea::CONTROLLI_PRIVACY,
            'type' => BusinessFunctionType::CONTROLLO,
            'mission' => 'Garantire la conformità al GDPR e la protezione dei dati degli interessati.',
        ]);

        // 2. Creazione Processo: "Gestione Data Breach"
        $process = Process::create([
            'company_id' => $companyId,
            'business_function_id' => $dpoFunction->id,
            'name' => 'Procedura Notifica Data Breach',
            'description' => 'Flusso di gestione in caso di violazione dei dati personali (Art. 33 GDPR).',
            'target_model' => 'App\Models\DataBreachLog',
            'is_active' => true,
        ]);

        $this->seedTasks($process, $dpoFunction, $companyId);
    }

    private function seedTasks($process, $dpoFunction, $companyId)
    {
        // Step 1: Analisi Preliminare della Violazione
        $task1 = ProcessTask::create([
            'company_id' => $companyId,
            'process_id' => $process->id,
            'business_function_id' => $dpoFunction->id,
            'sequence_number' => 1,
            'name' => 'Analisi Impatto e Rischio',
            'description' => 'Valutazione della gravità della violazione per i diritti degli interessati.',
        ]);

        // Assegnazione RACI: DPO è Accountable (A) e Responsible (R)
        RaciAssignment::create([
            'company_id' => $companyId,
            'process_task_id' => $task1->id,
            'business_function_id' => $dpoFunction->id,
            'role' => 'A',
        ]);

        $this->seedChecklists($task1);
    }

    private function seedChecklists($task)
    {
        $checklist = Checklist::create([
            'process_task_id' => $task->id,
            'name' => 'Verifiche Obbligatorie Art. 33',
            'sort_order' => 1,
        ]);

        // Item 1: Sempre obbligatorio
        ChecklistItem::create([
            'checklist_id' => $checklist->id,
            'instruction' => "Identificare la data e l'ora esatta della scoperta della violazione.",
            'is_mandatory' => true,
            'sort_order' => 1,
        ]);

        // Item 2: Obbligatorio SOLO SE il cliente è straniero (Usa la tua ForeignerRule)
        ChecklistItem::create([
            'checklist_id' => $checklist->id,
            'instruction' => 'Verificare se è necessaria la notifica a un Garante Estero (Cross-border processing).',
            'is_mandatory' => false,
            'require_condition_class' => 'App\Rules\Bpm\ForeignerRule',
            'sort_order' => 2,
        ]);

        // Item 3: Regola per la nomina a Responsabile Esterno
        ChecklistItem::create([
            'checklist_id' => $checklist->id,
            'instruction' => "Firmare l'addendum ex Art. 28 GDPR (Data Processing Agreement).",
            'is_mandatory' => false,
            'require_condition_class' => 'App\Rules\Privacy\ExternalProcessorRule',
            'sort_order' => 3,
        ]);
    }
}
