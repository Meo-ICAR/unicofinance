<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    protected static $counter = 1;

    public function definition(): array
    {
        $counter = self::$counter++;
        return [
            'name' => "Test Company {$counter}",
            'vat_number' => "IT".str_pad($counter, 11, '0', STR_PAD_LEFT),
            'vat_name' => "Test VAT Name {$counter}",
            'oam' => str_pad($counter, 8, '0', STR_PAD_LEFT),
            'oam_at' => now()->subYears(2),
            'oam_name' => "OAM Name {$counter}",
            'numero_iscrizione_rui' => "R".str_pad($counter, 9, '0', STR_PAD_LEFT),
            'ivass' => str_pad($counter, 8, '0', STR_PAD_LEFT),
            'ivass_at' => now()->subYears(2),
            'ivass_name' => "IVASS Name {$counter}",
            'ivass_section' => 'A',
            'sponsor' => "Sponsor {$counter}",
            'company_type' => 'mediatore',
            'page_header' => 'Test page header',
            'page_footer' => 'Test footer',
            'smtp_host' => 'smtp.test.com',
            'smtp_port' => 587,
            'smtp_username' => "test{$counter}@test.com",
            'smtp_password' => 'password',
            'smtp_encryption' => 'tls',
            'smtp_from_email' => "noreply{$counter}@test.com",
            'smtp_from_name' => "Test Company {$counter}",
            'smtp_enabled' => false,
            'smtp_verify_ssl' => true,
        ];
    }
}
