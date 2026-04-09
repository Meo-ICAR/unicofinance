<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 30% dei record hanno più di 24 mesi (per testare data retention)
        $createdAt = $this->faker->boolean(30) 
            ? now()->subMonths(rand(25, 48)) 
            : $this->faker->dateTimeBetween('-23 months', 'now');

        return [
            'company_id' => \App\Models\Company::first()?->id ?? \App\Models\Company::factory(),
            'is_person' => true,
            'name' => $this->faker->lastName(),
            'first_name' => $this->faker->firstName(),
            'tax_code' => $this->faker->unique()->regexify('[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]'),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'cliente',
            'is_lead' => false,
            'is_client' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_lead' => true,
            'is_client' => false,
            'status' => 'lead',
        ]);
    }

    public function purchaser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_person' => false,
            'is_lead' => false,
            'is_client' => true,
            'name' => $this->faker->company() . ' S.r.l.',
            'first_name' => null,
            'tax_code' => null,
            'vat_number' => $this->faker->vat(),
            'roc_registration_number' => $this->faker->regexify('[0-9]{5}'), // ROC a 5 cifre
            'dpo_email' => 'dpo@' . $this->faker->domainName(),
            'privacy_policy_url' => $this->faker->url(),
            'contract_signed_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
            'status' => 'cliente',
        ]);
    }
}
