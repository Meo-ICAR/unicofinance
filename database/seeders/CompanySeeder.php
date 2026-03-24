<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the tables
        DB::table('company_user')->truncate();
        DB::table('companies')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Insert/update the sample company
        Company::updateOrCreate([
            'name' => 'Hassisto'
        ], [
            'id' => Str::uuid(),
            'name' => 'Hassisto',
            'vat_number' => '0901836367764',
            'vat_name' => 'Hassisto',
            'ivass' => null,
            'ivass_at' => null,
            'ivass_name' => null,
            'ivass_section' => null,
            'sponsor' => null,
            'page_header' => '<p></p>',
            'page_footer' => '<p></p>',
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_encryption' => null,
            'smtp_from_email' => null,
            'smtp_from_name' => null,
            'smtp_enabled' => false,
            'smtp_verify_ssl' => true,
        ]);
        Company::updateOrCreate([
            'name' => 'Races Finance S.r.l.'
        ], [
            'id' => Str::uuid(),
            'name' => 'Races Finance S.r.l.',
            'vat_number' => '05822361007',
            'vat_name' => 'Races Finance',
            'oam' => 'M510',
            'oam_at' => '2012-11-26',
            'oam_name' => 'RACES FINANCE SRL',
            'numero_iscrizione_rui' => 'E000689226',
            'ivass' => null,
            'ivass_at' => null,
            'ivass_name' => null,
            'ivass_section' => null,
            'sponsor' => null,
            'company_type' => 'mediatore',
            'page_header' => '<p></p>',
            'page_footer' => '<p></p>',
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_encryption' => null,
            'smtp_from_email' => null,
            'smtp_from_name' => null,
            'smtp_enabled' => false,
            'smtp_verify_ssl' => true,
        ]);
    }
}
