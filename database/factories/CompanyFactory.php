<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'vat_number' => $this->faker->numerify('IT###########'),
            'vat_name' => $this->faker->company(),
            'oam' => $this->faker->numerify('########'),
            'oam_at' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'oam_name' => $this->faker->name(),
            'numero_iscrizione_rui' => $this->faker->numerify('R########'),
            'ivass' => $this->faker->numerify('########'),
            'ivass_at' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'ivass_name' => $this->faker->company(),
            'ivass_section' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'sponsor' => $this->faker->company(),
            'company_type' => $this->faker->randomElement(['mediatore', 'call center', 'hotel', 'sw house']),
            'page_header' => $this->faker->sentence(),
            'page_footer' => $this->faker->sentence(),
            'smtp_host' => $this->faker->domainName(),
            'smtp_port' => $this->faker->numberBetween(25, 587),
            'smtp_username' => $this->faker->email(),
            'smtp_password' => $this->faker->password(),
            'smtp_encryption' => $this->faker->randomElement(['tls', 'ssl', null]),
            'smtp_from_email' => $this->faker->companyEmail(),
            'smtp_from_name' => $this->faker->company(),
            'smtp_enabled' => $this->faker->boolean(50),
            'smtp_verify_ssl' => $this->faker->boolean(80),
        ];
    }
}
