<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'name' => 'Approvazione Standard',
                'process_type' => 'approval',
                'duration_minutes' => 2880, // 48 ore
                'warning_threshold_minutes' => 1440, // 24 ore prima
                'exclude_weekends' => true,
            ],
            [
                'name' => 'Verifica Documentale',
                'process_type' => 'document_check',
                'duration_minutes' => 720, // 12 ore
                'warning_threshold_minutes' => 480, // 8 ore prima
                'exclude_weekends' => false,
            ],
            [
                'name' => 'Data Breach - Urgente',
                'process_type' => 'data_breach',
                'duration_minutes' => 2880, // 48 ore (GDPR requirement)
                'warning_threshold_minutes' => 1440, // 24 ore prima
                'exclude_weekends' => false, // I data breach non aspettano il weekend
            ],
            [
                'name' => 'Onboarding Dipendente',
                'process_type' => 'employee_onboarding',
                'duration_minutes' => 10080, // 7 giorni
                'warning_threshold_minutes' => 4320, // 3 giorni prima
                'exclude_weekends' => true,
            ],
            [
                'name' => 'Revisione Compliance',
                'process_type' => 'compliance_review',
                'duration_minutes' => 4320, // 72 ore
                'warning_threshold_minutes' => 2160, // 36 ore prima
                'exclude_weekends' => true,
            ],
            [
                'name' => 'Richiesta Cliente',
                'process_type' => 'client_request',
                'duration_minutes' => 1440, // 24 ore
                'warning_threshold_minutes' => 720, // 12 ore prima
                'exclude_weekends' => false,
            ],
        ];

        foreach ($policies as $policy) {
            SlaPolicy::updateOrCreate(
                ['name' => $policy['name']],
                $policy
            );
        }
    }
}
