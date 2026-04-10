<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Create users if they don't exist
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'hassistosrl@gmail.com',
                'is_super_admin' => true,
            ],
            [
                'name' => 'Sergio Bracale',
                'email' => 'sergio.bracale@races.it',
                'is_super_admin' => false,
            ],
            [
                'name' => 'Mario',
                'email' => 'mario@globaladvisory.it',
                'is_super_admin' => false,
            ],
        ];

        foreach ($users as $userData) {
            if (!User::where('email', $userData['email'])->exists()) {
                $user = User::factory()->create($userData);
                $user->save();
            }
        }

        $this->call([
            CompanySeeder::class,
            CompanyUserSeeder::class,
            BusinessFunctionSeeder::class,
            CompanyBranchSeeder::class,
            EmployeeSeeder::class,
            ClientTypeSeeder::class,
            PrivacyDataTypeSeeder::class,
            PrivacyProcessesSeeder::class,
            BusinessPrivacySeeder::class,
            PrivacyBpmSeeder::class,
            ProcessTaskPrivacyDataSeeder::class,
            // Vocal Order Contract Acquisition (ARERA/GDPR Compliance)
            VocalOrderProcessSeeder::class,
            // Opt-Out & Blacklist Emergency Flow (Art. 21 GDPR)
            OptOutManagementProcessSeeder::class,
            // Request Registry (GDPR Art. 12, 15-22)
            RequestRegistrySeeder::class,
            SlaPolicySeeder::class,
            // GDPR Compliance & Workflow Data
            PrivacyComplianceSeeder::class,
            LeadCessionWorkflowSeeder::class,
            // BPM GDPR Processes
            LeadTransferProcessSeeder::class,
            ErasureRequestProcessSeeder::class,
            CampaignPrivacyByDesignProcessSeeder::class,
            VendorOnboardingProcessSeeder::class,
            LeadAcquisitionProcessSeeder::class,
            FacebookLeadAcquisitionSeeder::class,
            DataBreachProcessSeeder::class,
            // Process Macro Categories
            ProcessMacroCategorySeeder::class,
            // GDPR Access Request Process
            GdprAccessRequestProcessSeeder::class,
            // Process Request Mappings
            ProcessRequestMappingSeeder::class,
            // Log e Audit
            ConsentLogSeeder::class,
            DataBreachSeeder::class,
            // Lead Management
            LeadTransferSeeder::class,
            LeadReturnLogSeeder::class,
            // Privacy & Compliance
            SuppressionListSeeder::class,
            PrivacyActionSeeder::class,
            // Processi Aggiuntivi
            OutboundCallArt14ProcessSeeder::class,
            ListSanitizationRPOProcessSeeder::class,
        ]);
    }
}
