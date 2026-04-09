<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Client;
use App\Models\Company;
use App\Models\LeadTransfer;
use App\Models\LeadReturnLog;

class LeadCessionWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        // 1. CLIENT BLOCCATO: Senza contratto firmato
        // Questo record serve per testare le logiche di blocco preventivo
        Client::factory()->purchaser()->create([
            'company_id' => $company->id,
            'name' => 'Call Center Unauthorized Srl',
            'contract_signed_at' => null,
            'roc_registration_number' => '00000',
        ]);

        // 2. CLIENT VALIDI: Con contratto firmato
        $validClients = Client::factory()->count(5)->purchaser()->create([
            'company_id' => $company->id,
            'contract_signed_at' => now()->subMonths(3),
        ]);

        // 3. LEAD (I soggetti interessati)
        $leads = Client::factory()->count(50)->lead()->create(['company_id' => $company->id]);

        // 4. GENERAZIONE FLUSSI DI CESSIONE E RITORNO
        foreach ($leads as $lead) {
            $client = $validClients->random();

            // Log di Cessione (Invio verso Call Center)
            LeadTransfer::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'purchaser_id' => $client->id,
                'transferred_at' => now()->subDays(rand(1, 15)),
                'transfer_method' => collect(['api_tls', 'sftp', 'encrypted_csv'])->random(),
                'price' => rand(10, 30),
            ]);

            // Log di Ritorno (Feedback dal Call Center)
            // Distribuzione: 20% opt-out, 10% bounce, 70% converted/other
            $status = collect(['opt_out_requested', 'bounce', 'converted', 'converted', 'converted'])->random();
            
            LeadReturnLog::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'lead_id' => $lead->id,
                'status' => $status,
                'reported_at' => now()->subDays(rand(1, 5)),
            ]);
        }
    }
}
