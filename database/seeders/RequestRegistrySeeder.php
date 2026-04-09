<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RequestRegistry;
use App\Models\RequestRegistryAction;
use App\Models\RequestRegistryProcess;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestRegistrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $company = Company::first();

            if (!$company) {
                $this->command->error('Nessuna azienda trovata. Eseguire prima il CompanySeeder.');

                return;
            }

            $assignedTo = User::first()?->id;

            // ── Dati soggetti interessati di esempio ────────────────────────
            $client = Client::first();
            $employee = Employee::first();

            // ── Dati processi di esempio ────────────────────────────────────
            $optOutProcess = Process::where('name', 'like', '%Opt-Out%')->first();
            $optOutTask1 = $optOutProcess
                ? ProcessTask::where('process_id', $optOutProcess->id)->where('sequence_number', 1)->first()
                : null;

            // ── 1. Richiesta di Cancellazione da Interessato diretto ───────
            $req1 = RequestRegistry::create([
                'company_id' => $company->id,
                'request_number' => RequestRegistry::generateRequestNumber(),
                'request_date' => now()->subDays(25),
                'received_via' => 'pec',
                'requester_type' => 'interessato',
                'requester_name' => $client ? $client->name : 'Marco Rossi',
                'requester_contact' => 'marco.rossi@email.it',
                'request_type' => 'cancellazione',
                'data_subject_type' => $client ? Client::class : null,
                'data_subject_id' => $client?->id,
                'description' => 'L\'interessato richiede la cancellazione di tutti i dati personali in possesso del titolare, ai sensi dell\'Art. 17 GDPR.',
                'status' => 'in_lavorazione',
                'response_deadline' => now()->subDays(25)->addDays(30),
                'assigned_to' => $assignedTo,
                'notes' => 'Verificare se esistono dati conservati per obbligo legale prima di procedere alla cancellazione totale.',
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req1->id,
                'action_date' => now()->subDays(24),
                'action_type' => 'assegnazione',
                'description' => 'Richiesta assegnata al team Privacy per valutazione.',
                'performed_by' => $assignedTo,
            ]);

            // ── 2. Opposizione tramite Mandatario (avvocato) ───────────────
            $req2 = RequestRegistry::create([
                'company_id' => $company->id,
                'request_number' => RequestRegistry::generateRequestNumber(),
                'request_date' => now()->subDays(15),
                'received_via' => 'raccomandata',
                'requester_type' => 'mandatario',
                'requester_name' => 'Avv. Giulia Bianchi',
                'requester_contact' => 'avv.bianchi@studiolegale.it',
                'mandate_reference' => 'Procura speciale datata 10/01/2026, Notaio Rossi di Milano, Rep. 4521',
                'request_type' => 'opposizione',
                'data_subject_type' => $client ? Client::class : null,
                'data_subject_id' => $client?->id,
                'description' => 'Il mandatario, in nome e per conto del proprio assistito, chiede l\'immediata cessazione di ogni trattamento telefonico ai sensi dell\'Art. 21 GDPR e dell\'Art. 130 D.Lgs. 196/2003.',
                'status' => 'evasa',
                'response_deadline' => now()->subDays(15)->addDays(30),
                'response_date' => now()->subDays(3),
                'response_summary' => 'Opposizione accolta. Il numero è stato inserito nella Suppression List globale. Comunicato di conferma inviato al mandatario.',
                'assigned_to' => $assignedTo,
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req2->id,
                'action_date' => now()->subDays(14),
                'action_type' => 'assegnazione',
                'description' => 'Verifica procura e apertura pratica.',
                'performed_by' => $assignedTo,
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req2->id,
                'action_date' => now()->subDays(10),
                'action_type' => 'evasione',
                'description' => 'Numero inserito in blacklist. Invio comunicazione di avvenuta cancellazione al mandatario via PEC.',
                'performed_by' => $assignedTo,
            ]);

            // Collegamento al processo Opt-Out
            if ($optOutProcess && $optOutTask1) {
                RequestRegistryProcess::create([
                    'registry_id' => $req2->id,
                    'process_id' => $optOutProcess->id,
                    'process_task_id' => $optOutTask1->id,
                    'outcome' => 'Numero inserito in Suppression List. Comunicazione PEC inviata.',
                    'completed_at' => now()->subDays(10),
                ]);
            }

            // ── 3. Richiesta dal Garante Privacy (organismo vigilanza) ─────
            $req3 = RequestRegistry::create([
                'company_id' => $company->id,
                'request_number' => RequestRegistry::generateRequestNumber(),
                'request_date' => now()->subDays(8),
                'received_via' => 'pec',
                'requester_type' => 'organismo_vigilanza',
                'requester_name' => 'Garante per la Protezione dei Dati Personali',
                'requester_contact' => 'protocollo@gpdp.it',
                'oversight_body_type' => 'Garante Privacy',
                'request_type' => 'accesso',
                'data_subject_type' => $employee ? Employee::class : null,
                'data_subject_id' => $employee?->id,
                'description' => 'Il Garante richiede copia di tutti i dati personali trattati in relazione al dipendente indicato, nell\'ambito di un\'ispezione in corso. Risposta entro 15 giorni.',
                'status' => 'in_lavorazione',
                'response_deadline' => now()->subDays(8)->addDays(15),
                'extension_granted' => false,
                'assigned_to' => $assignedTo,
                'notes' => 'PRIORITÀ ASSOLUTA. Termine ridotto a 15 gg come richiesto dal Garante. Coinvolgere il legale aziendale.',
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req3->id,
                'action_date' => now()->subDays(7),
                'action_type' => 'assegnazione',
                'description' => 'Richiesta dal Garante: escalation immediata al DPO e al legale.',
                'performed_by' => $assignedTo,
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req3->id,
                'action_date' => now()->subDays(5),
                'action_type' => 'inoltro',
                'description' => 'Documentazione raccolta in corso. Inviata richiesta all\'HR per il fascicolo completo del dipendente.',
                'performed_by' => $assignedTo,
            ]);

            // ── 4. Richiesta di Portabilità — scaduta ───────────────────────
            $req4 = RequestRegistry::create([
                'company_id' => $company->id,
                'request_number' => RequestRegistry::generateRequestNumber(),
                'request_date' => now()->subDays(45),
                'received_via' => 'email',
                'requester_type' => 'interessato',
                'requester_name' => $client ? $client->first_name . ' ' . $client->name : 'Laura Verdi',
                'requester_contact' => 'laura.verdi@email.it',
                'request_type' => 'portabilita',
                'data_subject_type' => $client ? Client::class : null,
                'data_subject_id' => $client?->id,
                'description' => 'Richiesta di ricevere i propri dati in formato strutturato, di uso comune e leggibile da dispositivo automatico, ai sensi dell\'Art. 20 GDPR.',
                'status' => 'scaduta',
                'response_deadline' => now()->subDays(45)->addDays(30),
                'sla_breach' => true,
                'assigned_to' => $assignedTo,
                'notes' => 'SCADUTA — da gestire con urgenza. Rischio sanzione. Preparare export dati in formato CSV.',
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req4->id,
                'action_date' => now()->subDays(40),
                'action_type' => 'assegnazione',
                'description' => 'Richiesta ricevuta. Da gestire entro 30 giorni.',
                'performed_by' => $assignedTo,
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req4->id,
                'action_date' => now()->subDays(10),
                'action_type' => 'reclamo_interno',
                'description' => 'ALERT: termine di 30 giorni superato. Preparare risposta urgente e valutare autosegnalazione al Garante.',
                'performed_by' => $assignedTo,
            ]);

            // ── 5. Revoca consenso da parte di dipendente ───────────────────
            $req5 = RequestRegistry::create([
                'company_id' => $company->id,
                'request_number' => RequestRegistry::generateRequestNumber(),
                'request_date' => now()->subDays(3),
                'received_via' => 'di_persona',
                'requester_type' => 'interessato',
                'requester_name' => $employee ? $employee->name : 'Francesco Neri',
                'requester_contact' => 'f.neri@azienda.it',
                'request_type' => 'revoca_consenso',
                'data_subject_type' => $employee ? Employee::class : null,
                'data_subject_id' => $employee?->id,
                'description' => 'Il dipendente revoca il consenso al trattamento dei dati per finalità di marketing interno e sondaggi di soddisfazione.',
                'status' => 'ricevuta',
                'response_deadline' => now()->subDays(3)->addDays(30),
                'assigned_to' => $assignedTo,
            ]);

            RequestRegistryAction::create([
                'registry_id' => $req5->id,
                'action_date' => now()->subDays(3),
                'action_type' => 'assegnazione',
                'description' => 'Revoca registrata. Da verificare quali trattamenti sono basati sul consenso e quali su altre basi giuridiche.',
                'performed_by' => $assignedTo,
            ]);

            $this->command->info('✅ RequestRegistrySeeder completato: 5 richieste di esempio create.');
        });
    }
}
