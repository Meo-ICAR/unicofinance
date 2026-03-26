<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Enums\MacroArea;
use App\Enums\BusinessFunctionType;
use App\Enums\OutsourcableStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessFunctionSeeder extends Seeder
{
    public function run(): void
    {
        $functions = [
            [
                'code' => 'GOV-CDA',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Consiglio di Amministrazione / Direzione',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => 'Definisce strategie, approva procedure organizzative, politiche di rischio e assicura l’adeguatezza dell’assetto organizzativo.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => null,
                'mission' => null,
                'responsibility' => null,
            ],
            [
                'code' => 'BUS-DIRCOM',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Direzione Commerciale',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Sviluppo accordi con Banche/Finanziarie, monitoraggio volumi e coordinamento Area Manager.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO', // Non presente nei dati, lo legherà a NULL
                'mission' => "Garantire, in coerenza con le strategie aziendali, il raggiungimento degli obiettivi di produzione, di redditività e di rischio nei confronti dei convenzionati.\nAssicurare la gestione, l’animazione e l’assistenza ai convenzionati.\nGarantire il coordinamento operativo delle risorse allocate sulla Rete Commerciale.\nGarantire un adeguato supporto analitico e quantitativo.",
                'responsibility' => "• Supervisiona il raggiungimento degli obiettivi di produzione e redditività stabiliti a livello strategico.\n• Gestisce e anima le risorse della rete definendo le priorità di intervento.\n• Coordina l'operatività commerciale in conformità al modello di business.",
            ],
            [
                'code' => 'BUS-RETE-GEST',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Gestione Rete e Collaboratori',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Selezione, iscrizione elenchi OAM e monitoraggio dell’operato dei collaboratori esterni.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => null,
            ],
            [
                'code' => 'BUS-RETE-EXT',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Gestione Rete e Collaboratori',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Agenti e collaboratori sul territorio: vendita, relazione cliente e raccolta documentale primaria.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => null,
            ],
            [
                'code' => 'BUS-BO',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Back Office / Istruttoria Pratiche',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Istruttoria, controlli di I livello, caricamento portali bancari e gestione benestari CQS.',
                'outsourcable_status' => OutsourcableStatus::PARTIAL,
                'managed_by_code' => null,
            ],
            [
                'code' => 'SUP-AMM',
                'macro_area' => MacroArea::SUPPORTO,
                'name' => 'Amministrazione e Contabilità',
                'type' => BusinessFunctionType::SUPPORTO,
                'description' => 'Contabilità, fatturazione provvigioni attive/passive e gestione flussi finanziari.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => null,
            ],
            [
                'code' => 'SUP-IT',
                'macro_area' => MacroArea::SUPPORTO,
                'name' => 'IT e Sicurezza Dati',
                'type' => BusinessFunctionType::SUPPORTO,
                'description' => 'Gestione CRM, sicurezza informatica e continuità operativa.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => null,
            ],
            [
                'code' => 'SUP-RECLAMI',
                'macro_area' => MacroArea::SUPPORTO,
                'name' => 'Gestione Reclami e Controversie',
                'type' => BusinessFunctionType::SUPPORTO,
                'description' => 'Analisi reclami, gestione ricorsi ABF e reporting per la Direzione.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => null,
            ],
            [
                'code' => 'CTRL-COMPL',
                'macro_area' => MacroArea::CONTROLLI_2_LIVELLO,
                'name' => 'Compliance (Conformità)',
                'type' => BusinessFunctionType::CONTROLLO,
                'description' => 'Prevenzione del rischio di non conformità normativa (Trasparenza, OAM, Privacy).',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => 'GOV-CEO',
                'mission' => "Assicurare la predisposizione del Regolamento della Funzione di Compliance.\nGarantire il rispetto della legalità e della correttezza negli affari.\nAssistere l’Azienda su ogni problematica attinente.",
                'responsibility' => "• Formalizzazione Compliance Plan.\n• Rapporto agli organi aziendali.\n• Testing periodici.\n• Valutazione normativa applicabile.",
            ],
            [
                'code' => 'CTRL-AML',
                'macro_area' => MacroArea::CONTROLLI_2_LIVELLO,
                'name' => 'Antiriciclaggio (AML)',
                'type' => BusinessFunctionType::CONTROLLO,
                'description' => 'Profilatura rischio, tenuta AUI, analisi operazioni sospette e segnalazioni SOS.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => 'GOV-CDA',
                'mission' => "Garantire gli adempimenti previsti in materia di antiriciclaggio.",
                'responsibility' => "• Segnalazione sos.\n• Tenuta AUI.\n• Formazione reti e dipendenti.",
            ],
            [
                'code' => 'CTRL-AUDIT',
                'macro_area' => MacroArea::CONTROLLI_3_LIVELLO,
                'name' => 'Internal Audit (Revisione Interna)',
                'type' => BusinessFunctionType::CONTROLLO,
                'description' => 'Ispezioni indipendenti e test a campione su tutto l’impianto organizzativo.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => 'GOV-CDA',
                'mission' => "Garantire il monitoraggio e la corretta amministrazione dell’Azienda nel pieno rispetto delle norme.",
                'responsibility' => "• Audit ispettivi.\n• Reporting anomalie.\n• Esame costante dei processi aziendali.",
            ],
            [
                'code' => 'CTRL-DPO',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Data Protection Officer (DPO)',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => "Regolamento Privacy e Registro Trattamento.",
                'responsibility' => "• Monitoraggio adempimenti GDPR.",
            ],
            [
                'code' => 'SUP-LEG-AMM',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Amministrazione e Contabilità', // Sembra duplicato ma lo mappiamo come dal dict
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => "Supporto societario e registrazione contabile.",
                'responsibility' => "• Gestione polizze.\n• Precontenzioso.",
            ],
            [
                'code' => 'SUP-ORG',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Risorse Umane (HR) e Formazione',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => "Eseguire una efficiente gestione del personale (CCNL).",
                'responsibility' => "• Manutenzione policy aziendali.",
            ],
            [
                'code' => 'SUP-PLAN',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Marketing e Comunicazione',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => "Garantire il processo di pianificazione strategica.",
                'responsibility' => "• Condivisione Piano Strategico.",
            ],
        ];

        DB::transaction(function () use ($functions) {
            // Fase 1: Inserimento Dizionario base
            foreach ($functions as $data) {
                $payload = $data;
                unset($payload['managed_by_code']);

                BusinessFunction::updateOrCreate(
                    ['code' => $data['code']],
                    $payload
                );
            }

            // Fase 2: Costruzione Albero Gerarchico Relazionale
            foreach ($functions as $data) {
                if (!empty($data['managed_by_code'])) {
                    $parent = BusinessFunction::where('code', $data['managed_by_code'])->first();
                    if ($parent) {
                        BusinessFunction::where('code', $data['code'])->update([
                            'managed_by_id' => $parent->id
                        ]);
                    }
                }
            }
            // Fase 3: Associazione Company
            $companies = Company::all();
            foreach ($companies as $company) {
                $functions = BusinessFunction::where('company_id', null)->get();
                foreach ($functions as $function) {
                    BusinessFunction::CreateorUpdate([
                        'company_id' => $company->id,
                        'code' => $function['code'],
                        'macro_area' => $function['macro_area'],
                        'name' => $function['name'],
                    'type' => $company->type,
                    'description' => $company->description,
                    'outsourcable_status' => $company->outsourcable_status,
                    'managed_by_id' => $company->managed_by_id,
                    'mission' => $company->mission,
                    'responsibility' => $company->responsibility,
                ]);
            }
        }
        });
    }
}
