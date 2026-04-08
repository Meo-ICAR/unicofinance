<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessTaskPrivacyDataSeeder extends Seeder
{
    public function run(): void
    {

        $bases = [
            [
                'name' => 'Consenso',
                'reference_article' => 'Art. 6 par. 1 lett. a)',
                'description' => 'L\'interessato ha espresso il consenso al trattamento dei propri dati personali per una o più specifiche finalità.',
            ],
            [
                'name' => 'Esecuzione Contratto',
                'reference_article' => 'Art. 6 par. 1 lett. b)',
                'description' => 'Il trattamento è necessario all\'esecuzione di un contratto di cui l\'interessato è parte o all\'esecuzione di misure precontrattuali.',
            ],
            [
                'name' => 'Obbligo Legale',
                'reference_article' => 'Art. 6 par. 1 lett. c)',
                'description' => 'Il trattamento è necessario per adempiere un obbligo legale al quale è soggetto il titolare del trattamento.',
            ],
            [
                'name' => 'Interesse Vitale',
                'reference_article' => 'Art. 6 par. 1 lett. d)',
                'description' => 'Il trattamento è necessario per la salvaguardia degli interessi vitali dell\'interessato o di un\'altra persona fisica.',
            ],
            [
                'name' => 'Interesse Pubblico',
                'reference_article' => 'Art. 6 par. 1 lett. e)',
                'description' => 'Il trattamento è necessario per l\'esecuzione di un compito di interesse pubblico o connesso all\'esercizio di pubblici poteri.',
            ],
            [
                'name' => 'Legittimo Interesse',
                'reference_article' => 'Art. 6 par. 1 lett. f)',
                'description' => 'Il trattamento è necessario per il perseguimento del legittimo interesse del titolare del trattamento o di terzi.',
            ],
        ];

        foreach ($bases as $base) {
            DB::table('privacy_legal_bases')->updateOrInsert(
                ['name' => $base['name']],
                array_merge($base, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        // 1. Mappatura rapida delle Basi Giuridiche (per ID)
        $legalBases = DB::table('privacy_legal_bases')->pluck('id', 'name');

        // 2. Mappatura rapida dei Tipi di Dati (per Slug)
        $dataTypes = DB::table('privacy_data_types')->pluck('id', 'slug');

        $assignments = [
            // --- PROCESSO 1: Notifica Data Breach ---
            [
                'process_task_id' => 1, // Analisi Impatto e Rischio
                'privacy_data_type_id' => $dataTypes['ID_BASE'],
                'privacy_legal_base_id' => $legalBases['Obbligo Legale'],
                'access_level' => 'read',
                'purpose' => 'Valutazione dei rischi per gli interessati coinvolti nella violazione.',
                'is_encrypted' => true,
            ],

            // --- PROCESSO 2: Onboarding Dipendente ---
            [
                'process_task_id' => 2, // Raccolta documenti e firma contratto
                'privacy_data_type_id' => $dataTypes['ID_BASE'],
                'privacy_legal_base_id' => $legalBases['Esecuzione Contratto'],
                'access_level' => 'write',
                'purpose' => 'Anagrafica necessaria per la redazione del contratto di lavoro.',
                'is_encrypted' => false,
            ],
            [
                'process_task_id' => 2,
                'privacy_data_type_id' => $dataTypes['ID_GOV'],
                'privacy_legal_base_id' => $legalBases['Obbligo Legale'],
                'access_level' => 'write',
                'purpose' => 'Verifica identità e comunicazioni obbligatorie agli enti (Unilav).',
                'is_encrypted' => true,
            ],
            [
                'process_task_id' => 2,
                'privacy_data_type_id' => $dataTypes['HEALTH_DATA'],
                'privacy_legal_base_id' => $legalBases['Obbligo Legale'],
                'access_level' => 'write',
                'purpose' => 'Gestione idoneità alla mansione e quote categorie protette.',
                'is_encrypted' => true,
            ],
            [
                'process_task_id' => 3, // Consegna Informativa Privacy
                'privacy_data_type_id' => $dataTypes['ID_BASE'],
                'privacy_legal_base_id' => $legalBases['Obbligo Legale'],
                'access_level' => 'read',
                'purpose' => 'Tracciamento della avvenuta consegna dell\'informativa ex Art. 13.',
                'is_encrypted' => false,
            ],
            [
                'process_task_id' => 5, // Abilitazione Utenze IT
                'privacy_data_type_id' => $dataTypes['ID_BASE'],
                'privacy_legal_base_id' => $legalBases['Legittimo Interesse'],
                'access_level' => 'write',
                'purpose' => 'Creazione identità digitale aziendale e profili di accesso.',
                'is_encrypted' => true,
            ],

            // --- PROCESSO 3: Nomina Fornitore (DPA) ---
            [
                'process_task_id' => 6, // Vendor Security Assessment
                'privacy_data_type_id' => $dataTypes['ID_BASE'], // Dati dei referenti tecnici
                'privacy_legal_base_id' => $legalBases['Legittimo Interesse'],
                'access_level' => 'read',
                'purpose' => 'Verifica delle referenze e dei contatti tecnici del fornitore.',
                'is_encrypted' => false,
            ],
            [
                'process_task_id' => 7, // Stesura o Verifica del DPA
                'privacy_data_type_id' => $dataTypes['ID_BASE'],
                'privacy_legal_base_id' => $legalBases['Obbligo Legale'],
                'access_level' => 'write',
                'purpose' => 'Identificazione formale del Responsabile del Trattamento nel contratto DPA.',
                'is_encrypted' => false,
            ],
        ];

        foreach ($assignments as $row) {
            DB::table('process_task_privacy_data')->updateOrInsert(
                [
                    'process_task_id' => $row['process_task_id'],
                    'privacy_data_type_id' => $row['privacy_data_type_id'],
                ],
                array_merge($row, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }



    }
}
