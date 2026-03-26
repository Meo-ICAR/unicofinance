<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyBranch;
use Illuminate\Database\Seeder;

class CompanyBranchSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if ($company) {
            CompanyBranch::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Sede Legale Roma',
                ],
                [
                    'is_main_office' => true,
                    'manager_first_name' => 'Sergio',
                    'manager_last_name' => 'Bracale',
                    'manager_tax_code' => null,
                ]
            );
        }
    }
}
