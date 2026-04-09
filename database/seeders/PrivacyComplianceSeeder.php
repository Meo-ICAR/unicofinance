<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConsentLog;
use App\Models\DataBreach;
use App\Models\LeadTransfer;
use App\Models\SuppressionList;

class PrivacyComplianceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Assicuriamoci che esista almeno una Company (Tenant)
        $company = Company::first() ?? Company::factory()->create([
            'name' => 'Solar Power Italia Srl',
            'domain' => 'solarpoweritaly.it'
        ]);

        // 2. Creiamo i Clienti Acquirenti (Aziende/Installatori che comprano i lead)
        $purchasers = Client::factory()
            ->count(10)
            ->purchaser()
            ->create(['company_id' => $company->id]);

        // 3. Creiamo i Lead (I dati acquisiti dal List Provider)
        // La ClientFactory gestisce già il 30% di record "vecchi" (> 24 mesi)
        $leads = Client::factory()
            ->count(100)
            ->lead()
            ->create(['company_id' => $company->id]);

        // 4. Generiamo i Log del Consenso per ogni Lead
        foreach ($leads as $lead) {
            ConsentLog::factory()->create([
                'client_id' => $lead->id,
                'created_at' => $lead->created_at, // Coerente con la creazione del lead
            ]);
        }

        // 5. Simuliamo le Cessioni (Lead venduti agli acquirenti)
        // Prendiamo 40 lead a caso e li "vendiamo" ai nostri clienti installer
        $leads->random(40)->each(function ($lead) use ($purchasers) {
            LeadTransfer::factory()->create([
                'lead_id' => $lead->id,
                'purchaser_id' => $purchasers->random()->id,
                'transferred_at' => $lead->created_at->addDays(rand(1, 10)),
            ]);
        });

        // 6. Popoliamo la Suppression List (Esercizio dei diritti via email/telefono)
        SuppressionList::factory()->count(20)->create();

        // 7. Registriamo dei Data Breach storici per testare il registro trattamenti
        DataBreach::factory()->count(5)->create();
    }
}
