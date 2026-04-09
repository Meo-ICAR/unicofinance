<?php

namespace Database\Seeders;

use App\Enums\BusinessFunctionType;
use App\Enums\MacroArea;
use App\Enums\OutsourcableStatus;
use App\Models\BusinessFunction;
use App\Models\Company;
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
                // Campi Privacy
                'privacy_role' => 'Titolare del Trattamento',
                'purpose' => 'Definizione strategie aziendali, gestione societaria e adempimenti normativi di alto livello.',
                'data_subjects' => 'Dipendenti, Fornitori, Soci, Clienti',
                'data_categories' => 'Dati comuni, Dati finanziari, Dati particolari (in forma aggregata)',
                'retention_period' => '10 anni (obblighi di legge)',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Accessi logici ristretti, Segregazione dei ruoli direzionali',
                'privacy_data' => 'Dati strategici aziendali, Libri sociali, Verbali CdA',
            ],
            [
                'code' => 'BUS-DIRCOM',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Direzione Commerciale',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Sviluppo accordi con Banche/Finanziarie, monitoraggio volumi e coordinamento Area Manager.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => "Garantire, in coerenza con le strategie aziendali, il raggiungimento degli obiettivi di produzione, di redditività e di rischio nei confronti dei convenzionati.\nAssicurare la gestione, l’animazione e l’assistenza ai convenzionati.\nGarantire il coordinamento operativo delle risorse allocate sulla Rete Commerciale.\nGarantire un adeguato supporto analitico e quantitativo.",
                'responsibility' => "• Supervisiona il raggiungimento degli obiettivi di produzione e redditività stabiliti a livello strategico.\n• Gestisce e anima le risorse della rete definendo le priorità di intervento.\n• Coordina l'operatività commerciale in conformità al modello di business.",
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno',
                'purpose' => 'Gestione rete vendita, analisi redditività, reportistica volumi di affari.',
                'data_subjects' => 'Agenti, Collaboratori, Clienti (dati aggregati)',
                'data_categories' => 'Dati anagrafici, Dati contrattuali, Volumi generati',
                'retention_period' => '10 anni dalla cessazione del mandato',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Profilazione accessi CRM, Data Loss Prevention (DLP)',
                'privacy_data' => 'Performance di vendita, Anagrafiche convenzionati e Rete',
            ],
            [
                'code' => 'BUS-RETE-GEST',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Gestione Rete e Collaboratori',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Selezione, iscrizione elenchi OAM e monitoraggio dell’operato dei collaboratori esterni.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => null,
                // Campi Privacy
                'privacy_role' => 'Incaricato al Trattamento',
                'purpose' => 'Gestione amministrativa e normativa dei collaboratori, requisiti OAM.',
                'data_subjects' => 'Agenti, Mediatori creditizi, Collaboratori',
                'data_categories' => 'Dati anagrafici, Casellario giudiziale, Certificati OAM',
                'retention_period' => '10 anni dalla fine del rapporto',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Fascicolazione elettronica protetta, Accesso role-based',
                'privacy_data' => 'Dossier collaboratori, Certificazioni onorabilità e professionalità',
            ],
            [
                'code' => 'BUS-RETE-EXT',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Gestione Rete e Collaboratori (Esterna)',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Agenti e collaboratori sul territorio: vendita, relazione cliente e raccolta documentale primaria.',
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => null,
                // Campi Privacy
                'privacy_role' => 'Incaricato / Responsabile Esterno',
                'purpose' => 'Intermediazione commerciale, raccolta documenti per istruttoria pratiche.',
                'data_subjects' => 'Clienti finali',
                'data_categories' => 'Anagrafiche, Dati reddituali (CUD, buste paga), Dati di contatto',
                'retention_period' => 'Tempo strettamente necessario per il caricamento a sistema',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Crittografia in transito (HTTPS/VPN), Divieto di conservazione in locale',
                'privacy_data' => 'Documentazione reddituale e identificativa del cliente',
            ],
            [
                'code' => 'BUS-BO',
                'macro_area' => MacroArea::BUSINESS_COMMERCIALE,
                'name' => 'Back Office / Istruttoria Pratiche',
                'type' => BusinessFunctionType::OPERATIVA,
                'description' => 'Istruttoria, controlli di I livello, caricamento portali bancari e gestione benestari CQS.',
                'outsourcable_status' => OutsourcableStatus::PARTIAL,
                'managed_by_code' => null,
                // Campi Privacy
                'privacy_role' => 'Incaricato al Trattamento',
                'purpose' => 'Lavorazione pratiche di finanziamento, controlli antifrode, caricamento portali bancari.',
                'data_subjects' => 'Clienti finali',
                'data_categories' => 'Anagrafiche, Dati bancari (IBAN), CR/CRIF, Dati reddituali, Certificati medici (se CQS)',
                'retention_period' => '10 anni dalla chiusura della pratica',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Segregazione degli ambienti di test/produzione, Tracciamento log (audit trail)',
                'privacy_data' => 'Fascicolo cliente completo, visure, documenti finanziari',
            ],
            [
                'code' => 'SUP-AMM',
                'macro_area' => MacroArea::SUPPORTO,
                'name' => 'Amministrazione e Contabilità',
                'type' => BusinessFunctionType::SUPPORTO,
                'description' => 'Contabilità, fatturazione provvigioni attive/passive e gestione flussi finanziari.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => null,
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno',
                'purpose' => 'Fatturazione, pagamenti, tenuta scritture contabili.',
                'data_subjects' => 'Fornitori, Rete Commerciale, Clienti',
                'data_categories' => 'Dati fiscali, P.IVA, IBAN, Dati di fatturazione',
                'retention_period' => '10 anni (obblighi civilistico/fiscali)',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Accesso limitato al software gestionale, Backup periodici cifrati',
                'privacy_data' => 'Fatture, flussi bancari (Home Banking), Provvigioni',
            ],
            [
                'code' => 'SUP-IT',
                'macro_area' => MacroArea::SUPPORTO,
                'name' => 'IT e Sicurezza Dati',
                'type' => BusinessFunctionType::SUPPORTO,
                'description' => 'Gestione CRM, sicurezza informatica e continuità operativa.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => null,
                // Campi Privacy
                'privacy_role' => 'Amministratore di Sistema',
                'purpose' => 'Manutenzione infrastruttura, gestione account, esecuzione backup e disaster recovery.',
                'data_subjects' => 'Dipendenti, Rete, Clienti (indirettamente)',
                'data_categories' => 'Credenziali, Indirizzi IP, Log di navigazione e accesso',
                'retention_period' => '6 mesi (log accessi), per tutta la durata del contratto per le utenze',
                'extra_eu_transfer' => 'Eventuale (servizi Cloud, es. AWS/Azure - con clausole SCC)',
                'security_measures' => 'MFA, Firewall, IDS/IPS, Cifratura DB, Gestione PAM (Privileged Access Management)',
                'privacy_data' => 'Log di sistema, credenziali utente, archivi di backup',
            ],
            [
                'code' => 'SUP-RECLAMI',
                'macro_area' => MacroArea::SUPPORTO,
                'name' => 'Gestione Reclami e Controversie',
                'type' => BusinessFunctionType::SUPPORTO,
                'description' => 'Analisi reclami, gestione ricorsi ABF e reporting per la Direzione.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => null,
                // Campi Privacy
                'privacy_role' => 'Incaricato al Trattamento',
                'purpose' => 'Evasione reclami clienti, gestione contenziosi e ricorsi Arbitro Bancario Finanziario.',
                'data_subjects' => 'Clienti reclamanti',
                'data_categories' => 'Dati identificativi, Dettagli contrattuali, Motivi del reclamo',
                'retention_period' => '10 anni dalla definizione della controversia/reclamo',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Archivio elettronico riservato, pseudonimizzazione nei report direzionali',
                'privacy_data' => 'Dossier di reclamo, comunicazioni legali',
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
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno',
                'purpose' => 'Verifica conformità normativa, controlli trasparenza, aggiornamento contrattualistica.',
                'data_subjects' => 'Clienti, Dipendenti (in fase di test)',
                'data_categories' => 'Qualsiasi categoria trattata (a campione per i test)',
                'retention_period' => '5 anni (report di conformità)',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Accessi in sola lettura ai sistemi primari, data masking ove possibile',
                'privacy_data' => 'Pratiche campionate, Report di compliance',
            ],
            [
                'code' => 'CTRL-AML',
                'macro_area' => MacroArea::CONTROLLI_2_LIVELLO,
                'name' => 'Antiriciclaggio (AML)',
                'type' => BusinessFunctionType::CONTROLLO,
                'description' => 'Profilatura rischio, tenuta AUI, analisi operazioni sospette e segnalazioni SOS.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => 'GOV-CDA',
                'mission' => 'Garantire gli adempimenti previsti in materia di antiriciclaggio.',
                'responsibility' => "• Segnalazione sos.\n• Tenuta AUI.\n• Formazione reti e dipendenti.",
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno (Delegato SOS)',
                'purpose' => 'Adeguata verifica clientela, profilatura di rischio, invio SOS, tenuta Archivio Unico Informatico (AUI).',
                'data_subjects' => 'Clienti, Titolari Effettivi',
                'data_categories' => 'Documenti identità, Profilo PEP/PIL, Provenienza fondi, Dati giudiziari',
                'retention_period' => '10 anni (normativa Antiriciclaggio)',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Archivi blindati (logici/fisici), accesso limitato alla sola funzione AML',
                'privacy_data' => 'Questionari AML, Profilatura rischio, Segnalazioni Operazioni Sospette',
            ],
            [
                'code' => 'CTRL-AUDIT',
                'macro_area' => MacroArea::CONTROLLI_3_LIVELLO,
                'name' => 'Internal Audit (Revisione Interna)',
                'type' => BusinessFunctionType::CONTROLLO,
                'description' => 'Ispezioni indipendenti e test a campione su tutto l’impianto organizzativo.',
                'outsourcable_status' => OutsourcableStatus::YES,
                'managed_by_code' => 'GOV-CDA',
                'mission' => 'Garantire il monitoraggio e la corretta amministrazione dell’Azienda nel pieno rispetto delle norme.',
                'responsibility' => "• Audit ispettivi.\n• Reporting anomalie.\n• Esame costante dei processi aziendali.",
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno',
                'purpose' => 'Verifica ispettiva dell\'efficacia dei controlli aziendali.',
                'data_subjects' => 'Dipendenti, Rete, Clienti',
                'data_categories' => 'Intero patrimonio informativo aziendale (sotto campionamento)',
                'retention_period' => '5 anni (Reportistica Audit)',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Credenziali a validità temporanea per ispezioni, cifratura dei report',
                'privacy_data' => 'Evidenze ispettive, anomalie rilevate sui dati',
            ],
            [
                'code' => 'CTRL-DPO',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Data Protection Officer (DPO)',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => 'Regolamento Privacy e Registro Trattamento.',
                'responsibility' => '• Monitoraggio adempimenti GDPR.',
                // Campi Privacy
                'privacy_role' => 'DPO (Data Protection Officer)',
                'purpose' => 'Sorveglianza conformità normativa GDPR, gestione DPIA, punto di contatto Garante.',
                'data_subjects' => 'Tutti gli interessati',
                'data_categories' => 'Tutte le categorie',
                'retention_period' => 'Per l\'intera durata dell\'incarico',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Indipendenza organizzativa, Segretezza e confidenzialità (Art. 38 GDPR)',
                'privacy_data' => 'DPIA, Registri dei trattamenti, Comunicazioni Data Breach',
            ],
            [
                'code' => 'SUP-LEG-AMM',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Affari Legali e Societari', // Ho modificato il nome rispetto all'originale per differenziarlo da SUP-AMM
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => 'Supporto societario e registrazione contabile.',
                'responsibility' => "• Gestione polizze.\n• Precontenzioso.",
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno',
                'purpose' => 'Gestione legale, recupero crediti, contrattualistica istituzionale e assicurativa.',
                'data_subjects' => 'Controparti, Clienti morosi, Dipendenti',
                'data_categories' => 'Dati contrattuali, Situazioni patrimoniali/giudiziarie',
                'retention_period' => '10 anni dalla fine del contenzioso',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Archivio legale dedicato, cifratura documenti',
                'privacy_data' => 'Atti legali, denunce, polizze assicurative',
            ],
            [
                'code' => 'SUP-ORG',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Risorse Umane (HR) e Formazione',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => 'Eseguire una efficiente gestione del personale (CCNL).',
                'responsibility' => '• Manutenzione policy aziendali.',
                // Campi Privacy
                'privacy_role' => 'Responsabile Interno',
                'purpose' => 'Selezione, gestione del personale, payroll e formazione aziendale.',
                'data_subjects' => 'Dipendenti, Candidati',
                'data_categories' => 'Dati anagrafici, Dati bancari, Dati particolari (salute, maternità, sindacato)',
                'retention_period' => '10 anni post-cessazione (Curricula scartati: 24 mesi)',
                'extra_eu_transfer' => 'No',
                'security_measures' => 'Armadi ignifughi chiusi a chiave (cartaceo), Cartelle di rete HR con ACL ristrette',
                'privacy_data' => 'Fascicoli dipendenti, Buste paga, Certificati medici, CV',
            ],
            [
                'code' => 'SUP-PLAN',
                'macro_area' => MacroArea::GOVERNANCE,
                'name' => 'Marketing e Comunicazione',
                'type' => BusinessFunctionType::STRATEGICA,
                'description' => null,
                'outsourcable_status' => OutsourcableStatus::NO,
                'managed_by_code' => 'GOV-CEO',
                'mission' => 'Garantire il processo di pianificazione strategica.',
                'responsibility' => '• Condivisione Piano Strategico.',
                // Campi Privacy
                'privacy_role' => 'Incaricato al Trattamento',
                'purpose' => 'Promozione servizi, DEM (Direct Email Marketing), gestione campagne advertising.',
                'data_subjects' => 'Prospect, Clienti (previa acquisizione consenso)',
                'data_categories' => 'Dati di contatto, Preferenze/Comportamenti (se profilazione)',
                'retention_period' => '24 mesi (Marketing) / 12 mesi (Profilazione) dall\'ultimo contatto',
                'extra_eu_transfer' => 'Sì (Verso piattaforme es. Mailchimp, Meta - coperte da Data Privacy Framework / SCC)',
                'security_measures' => 'Gestione automatizzata opt-in/opt-out, Pseudonimizzazione data-lake',
                'privacy_data' => 'Mailing list, Consensi privacy, Statistiche di campagna',
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
                if (! empty($data['managed_by_code'])) {
                    $parent = BusinessFunction::where('code', $data['managed_by_code'])->first();
                    if ($parent) {
                        BusinessFunction::where('code', $data['code'])->update([
                            'managed_by_id' => $parent->id,
                        ]);
                    }
                }
            }

            // Fase 3: Associazione Company
            $companies = Company::all();
            foreach ($companies as $company) {
                $base_functions = BusinessFunction::where('company_id', null)->get();
                foreach ($base_functions as $f) {
                    BusinessFunction::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'code' => $f->code,
                        ],
                        [
                            'macro_area' => $f->macro_area->value,
                            'name' => $f->name,
                            'type' => $f->type->value,
                            'description' => $f->description,
                            'outsourcable_status' => $f->outsourcable_status->value,
                            'mission' => $f->mission,
                            'responsibility' => $f->responsibility,
                            // Copia anche i campi privacy
                            'privacy_role' => $f->privacy_role,
                            'purpose' => $f->purpose,
                            'data_subjects' => $f->data_subjects,
                            'data_categories' => $f->data_categories,
                            'retention_period' => $f->retention_period,
                            'extra_eu_transfer' => $f->extra_eu_transfer,
                            'security_measures' => $f->security_measures,
                            'privacy_data' => $f->privacy_data,
                        ]
                    );
                }
            }
        });
    }
}
