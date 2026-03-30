<?php

namespace Database\Seeders;

use App\Models\PrivacyDataType;
use Illuminate\Database\Seeder;

class PrivacyDataTypeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // CATEGORIA: IDENTIFICATIVI
            [
                'slug' => 'ID_BASE',
                'name' => 'Dati Anagrafici di Base',
                'category' => 'comuni',
                'retention_years' => 10,
            ],
            [
                'slug' => 'ID_GOV',
                'name' => 'Documenti di Identità / Codice Fiscale',
                'category' => 'comuni',
                'retention_years' => 10,
            ],
            // CATEGORIA: FINANZIARI
            [
                'slug' => 'FIN_BANK',
                'name' => 'Coordinate Bancarie (IBAN)',
                'category' => 'comuni',
                'retention_years' => 10,
            ],
            [
                'slug' => 'FIN_CREDIT',  // Corrisponde alla tua description
                'name' => 'Merito Creditizio / CRIF',
                'category' => 'comuni',
                'retention_years' => 5,
            ],
            // CATEGORIA: PARTICOLARI (EX SENSIBILI)
            [
                'slug' => 'HEALTH_DATA',  // Corrisponde alla tua description
                'name' => 'Stato di Salute / Dati Sanitari',
                'category' => 'particolari',
                'retention_years' => 10,
            ],
            [
                'slug' => 'POLITICAL_REL',
                'name' => 'Cariche Politiche (PEP) / Sindacali',
                'category' => 'particolari',
                'retention_years' => 10,
            ],
            // CATEGORIA: GIUDIZIARI
            [
                'slug' => 'CRIMINAL_REC',
                'name' => 'Casellario Giudiziale',
                'category' => 'giudiziari',
                'retention_years' => 10,
            ],
        ];

        foreach ($data as $item) {
            PrivacyDataType::updateOrCreate(
                ['slug' => $item['slug']],  // Chiave univoca
                $item
            );
        }
    }
}
