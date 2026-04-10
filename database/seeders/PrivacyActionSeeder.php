<?php
// Database/Seeders/PrivacyActionSeeder.php

public function run()
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
