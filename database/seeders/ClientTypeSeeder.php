<?php

namespace Database\Seeders;

use App\Models\ClientType;
use Illuminate\Database\Seeder;

class ClientTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Privato',     'description' => 'Persona fisica privata'],
            ['name' => 'PMI',         'description' => 'Piccola o Media Impresa'],
            ['name' => 'PA',          'description' => 'Pubblica Amministrazione'],
            ['name' => 'Azienda',     'description' => 'Azienda / Persona giuridica'],
            ['name' => 'Lead',        'description' => 'Contatto non ancora convertito'],
            ['name' => 'Professionista', 'description' => 'Libero professionista'],
        ];

        foreach ($types as $type) {
            ClientType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
