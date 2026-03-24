<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the test user and company
        $user = User::where('email', 'test@example.com')->first();
        $company = Company::where('id', 'd904fae6-702d-4965-95e5-667e066e46a8')->first();

        if ($user && $company) {
            // Check if relationship already exists
            $exists = DB::table('company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->exists();

            if (!$exists) {
                // Use the model relationship to attach
                $user->companies()->attach($company->id);

                $this->command->info('Attached user to company successfully.');
            } else {
                $this->command->info('User-company relationship already exists.');
            }
        } else {
            $this->command->error('Could not find user or company for seeding.');

            // Debug information
            if (!$user) {
                $this->command->error('Test user not found.');
            }
            if (!$company) {
                $this->command->error('Company not found. Available companies:');
                $companies = Company::all();
                foreach ($companies as $c) {
                    $this->command->line('ID: ' . $c->id . ', Name: ' . $c->name);
                }
            }
        }
    }
}
