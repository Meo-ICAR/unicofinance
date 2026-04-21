<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Registro;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegistroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company dynamically
        $company = Company::first();

        if (!$company) {
            $this->command->error('Nessuna azienda trovata. Eseguire prima il CompanySeeder.');
            return;
        }

        // Create the AUDIT_RETE registro
        Registro::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'AUDIT_RETE',
            ],
            [
                'description' => 'Audit rete vendita OAM',
                'last_number' => 1,
                'n_scheduled' => 5,
                'n_progress' => 0,
                'n_done' => 0,
                'from' => 1,
                'to' => 5,
                'date' => '2026-01-01',
            ]
        );

        $this->command->info('Registro seeded successfully!');
    }
}
