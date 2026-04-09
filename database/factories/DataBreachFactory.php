<?php

namespace Database\Factories;

use App\Models\DataBreach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataBreach>
 */
class DataBreachFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'discovery_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'description' => $this->faker->randomElement([
                'Accesso non autorizzato tramite credenziali compromesse (Phishing).',
                'Smarrimento di un dispositivo aziendale non cifrato contenente liste lead.',
                'Errore di configurazione del bucket S3 che ha esposto documenti per 4 ore.',
                'Invio massivo di email in CC anziché CCN a 150 destinatari.',
                'Attacco SQL Injection rilevato e bloccato, possibile esfiltrazione parziale.'
            ]),
            'severity_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'reported_to_dpa' => $this->faker->boolean(20),
        ];
    }
}
