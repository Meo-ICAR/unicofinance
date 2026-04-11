<?php
namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\PrivacyDataType;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use Illuminate\Database\Seeder;

class PrivacyActionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if (!$company) {
            $this->command->error('Nessuna azienda trovata. Eseguire prima il CompanySeeder.');
            return;
        }

        // Use the first existing process task for this checklist
        $processTask = ProcessTask::first();

        if (!$processTask) {
            $this->command->error('Nessun process task trovato. Eseguire prima i seeders dei processi.');
            return;
        }

        $checklist = Checklist::firstOrCreate(
            ['name' => 'Controlli Preliminari Privacy'],
            [
                'company_id' => $company->id,
                'process_task_id' => $processTask->id,
                'sort_order' => 1,
            ]
        );

        // 1. Task con invio email automatico
        ChecklistItem::firstOrCreate(
            [
                'checklist_id' => $checklist->id,
                'instruction' => 'Invia Informativa Privacy e richiedi firme digitali',
            ],
            [
                'company_id' => $company->id,
                'is_mandatory' => true,
                'sort_order' => 10,
                // Al completamento, scatta questa classe
                'action_class' => 'App\Actions\Bpm\SendPrivacyWelcomeEmail',
            ]
        );

        // 2. Task che modifica il record del Cliente
        ChecklistItem::firstOrCreate(
            [
                'checklist_id' => $checklist->id,
                'instruction' => 'Verifica conformità consensi marketing/profilazione',
            ],
            [
                'company_id' => $company->id,
                'is_mandatory' => true,
                'sort_order' => 20,
                // Al completamento, cambia lo stato del cliente nel DB
                'action_class' => 'App\Actions\Bpm\UpdateClientToAmlCheck',
            ]
        );
    }
}
