<?php
namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\PrivacyDataType;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use Illuminate\Database\Seeder;
use App\Models\Checklist;
use App\Models\ChecklistItem;

class PrivacyActionSeeder extends Seeder
{
    public function run(): void
    {
        $checklist = Checklist::where('name', 'Controlli Preliminari Privacy')->first();

        // 1. Task con invio email automatico
        ChecklistItem::create([
            'checklist_id' => $checklist->id,
            'instruction' => 'Invia Informativa Privacy e richiedi firme digitali',
            'is_mandatory' => true,
            'sort_order' => 10,
            // Al completamento, scatta questa classe
            'action_class' => 'App\Actions\Bpm\SendPrivacyWelcomeEmail',
        ]);

        // 2. Task che modifica il record del Cliente
        ChecklistItem::create([
            'checklist_id' => $checklist->id,
            'instruction' => 'Verifica conformità consensi marketing/profilazione',
            'is_mandatory' => true,
            'sort_order' => 20,
            // Al completamento, cambia lo stato del cliente nel DB
            'action_class' => 'App\Actions\Bpm\UpdateClientToAmlCheck',
        ]);
    }
}
