<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define user-company mappings
        $userCompanyMappings = [
            'hassistosrl@gmail.com' => 'Hassisto',
            'sergio.bracale@races.it' => 'Races Finance S.r.l.',
            'mario@globaladvisory.it' => 'Races Finance S.r.l.',
        ];

        foreach ($userCompanyMappings as $userEmail => $companyName) {
            $user = User::where('email', $userEmail)->first();
            $company = Company::where('name', $companyName)->first();

            if ($user && $company) {
                // Check if relationship already exists
                $exists = DB::table('company_user')
                    ->where('user_id', $user->id)
                    ->where('company_id', $company->id)
                    ->exists();

                if (! $exists) {
                    // Use the model relationship to attach
                    $user->companies()->attach($company->id, ['role' => 'admin']);

                    $this->command->info("Attached {$userEmail} to {$companyName} successfully.");
                } else {
                    $this->command->info("Relationship between {$userEmail} and {$companyName} already exists.");
                }
            } else {
                $this->command->error("Could not find user ({$userEmail}) or company ({$companyName}) for seeding.");
            }
        }
    }
}
