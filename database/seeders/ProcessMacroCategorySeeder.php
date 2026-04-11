<?php

namespace Database\Seeders;

use App\Models\ProcessMacroCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProcessMacroCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'CORE',
                'name' => 'Processi Operativi (Core / Business)',
                'description' => "Processi primari che generano direttamente valore per l'azienda. Includono attività di acquisizione lead, vendite, produzione ed erogazione dei servizi.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'COMPL',
                'name' => 'Processi di Controllo (Privacy & Compliance)',
                'description' => "Processi di garanzia che assicurano che l'azienda operi nel rispetto delle normative vigenti (es. GDPR, Qualità), mitigando i rischi legali e sanzionatori.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SUPP',
                'name' => 'Processi di Supporto e Governance',
                'description' => "Processi che non generano valore diretto per il cliente finale, ma forniscono le risorse umane, tecnologiche e amministrative necessarie al funzionamento dell'azienda.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($categories as $category) {
            ProcessMacroCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
