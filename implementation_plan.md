# Implementation Plan: OptOutManagementProcessSeeder

## Obiettivo

Creare un Database Seeder per il processo "Gestione Immediata Opt-Out e Blacklist (Art. 21 GDPR)" che mappi il flusso d'emergenza attivato quando un interessato revoca il consenso o si oppone al trattamento. Il processo garantisce l'inserimento istantaneo nella Suppression List e la notifica al Titolare del Trattamento (Utility/Committente).

## Architettura del Dati

```
Process (Gestione Immediata Opt-Out e Blacklist - Art. 21 GDPR)
├── ProcessTask 1: Interruzione e Registrazione Esito Assoluto
│   ├── Checklist: "Triage Opposizione"
│   │   ├── Item: Interrompere proposta commerciale (Diritto di Opposizione Assoluto)
│   │   └── Item: Registrare esito come 'OPT-OUT / RICHIESTA CANCELLAZIONE'
│   └── (nessuna mappatura privacy diretta — il dato è gestito dal Task 2)
│
├── ProcessTask 2: Inserimento in Suppression List Locale
│   ├── Checklist: "Azione Tecnica Dialer"
│   │   ├── Item: Sganciare numerazione dalla coda e dai ricontatti programmati
│   │   └── Item: Inserire hash del numero nella Suppression List globale
│   └── ProcessTaskPrivacyData
│       ├── privacy_data_type_id: 1 (ID_BASE – Numero di Telefono)
│       ├── privacy_legal_base_id: 3 (Obbligo Legale – Art. 6 par. 1 lett. c)
│       ├── access_level: 'update'
│       ├── purpose: "Pseudonimizzazione del record e inserimento nella Blacklist di blocco del Dialer."
│       ├── is_encrypted: true
│       └── is_shared_externally: false
│
└── ProcessTask 3: Notifica al Titolare / Utility
    ├── Checklist: "Sincronizzazione Committente"
    │   ├── Item: Tracciare il log temporale della richiesta di Opt-Out
    │   └── Item: Inserire record nel flusso SFTP di fine giornata per notifica revoca
    └── (nessuna mappatura privacy diretta — il dato è già gestito dal Task 2)
```

## Dettagli Implementativi

### 1. Processo Principale

- **name**: `Gestione Immediata Opt-Out e Blacklist (Art. 21 GDPR)`
- **description**: Procedura di inibizione immediata della numerazione a seguito di opposizione dell'interessato, con aggiornamento della Suppression List e comunicazione di ritorno al Committente.
- **target_model**: `App\Models\Blacklist`
- **company_id**: ricavato dalla prima azienda presente nel DB
- **business_function_id**: nullable (il reparto "Teleselling/IT" non è referenziato come BusinessFunction con codice noto; il campo verrà lasciato NULL, coerentemente con le convenzioni del progetto per i processi cross-funzione)

### 2. Task e Checklists

Ogni task viene creato con `updateOrCreate` per idempotenza, usando la combinazione `(company_id, process_id, sequence_number)` come chiave di unicità. Ogni checklist è creata con `updateOrCreate` su `(process_task_id, name, company_id)`. Ogni item usa `instruction` come chiave di unicità.

### 3. Mappatura Privacy – Analisi dell'Obbligo Legale (ID 3) per il Task 2

#### Perché Obbligo Legale (Art. 6 par. 1 lett. c)?

Il Task 2 ("Inserimento in Suppression List Locale") è l'unico task del processo che richiede una mappatura esplicita nella pivot `process_task_privacy_data`. La scelta della base giuridica **ID 3 – Obbligo Legale** è motivata da quanto segue:

| Aspetto                  | Dettaglio                                                                                                                                                                                                                                                                                                                   |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Dato trattato**        | Il numero di telefono dell'interessato (riconducibile a `ID_BASE`, categoria `comuni`)                                                                                                                                                                                                                                      |
| **Finalità**             | Conservare il numero in blacklist per **ricordare di NON chiamarlo più**                                                                                                                                                                                                                                                    |
| **Paradosso apparente**  | Si conserva un dato personale _nonostante_ la revoca del consenso                                                                                                                                                                                                                                                           |
| **Soluzione giuridica**  | Il Garante Privacy e l'Art. 130 del D.Lgs. 196/2003 (Codice Privacy) impongono al Titolare e al Responsabile di **tenere traccia delle opposizioni** per non ripetere il trattamento indesiderato. La conservazione del numero in Suppression List è quindi un **obbligo di legge**, non un trattamento basato sul consenso |
| **Base corretta**        | Art. 6 par. 1 lett. c) GDPR – "Il trattamento è necessario per adempiere un obbligo legale"                                                                                                                                                                                                                                 |
| **Access level**         | `update` — il numero viene inserito o aggiornato nella lista di blocco (non è una semplice lettura, né una cancellazione)                                                                                                                                                                                                   |
| **is_encrypted**         | `true` — il numero di telefono, anche se in blacklist, resta un dato personale e va protetto                                                                                                                                                                                                                                |
| **is_shared_externally** | `false` — la Suppression List è interna all'azienda; la notifica al Committente avviene tramite log SFTP separato (Task 3), non tramite condivisione della blacklist stessa                                                                                                                                                 |

#### Perché solo Task 2 e non anche Task 3?

Il Task 3 ("Notifica al Titolare / Utility") si limita a inserire un record in un log di esportazione SFTP. Il dato trattato è il **log temporale della richiesta** — un metadato operativo, non un dato personale dell'interessato in senso stretto. La mappatura privacy è quindi già coperta dal Task 2: il numero di telefono è già tutelato lì, e il log SFTP ne è una derivazione funzionale. Aggiungere una seconda mappatura sarebbe ridondante.

### 4. Idempotenza

Tutte le operazioni usano `updateOrCreate` (modelli Eloquent) o `updateOrInsert` (pivot table) per garantire che il seeder possa essere eseguito più volte senza duplicare record.

### 5. Transazioni

L'intero seeding è avvolto in `DB::transaction()` per atomicità: se un solo passaggio fallisce, nessun record viene inserito.

## Dipendenze

- `CompanySeeder` deve essere eseguito prima (per avere almeno un'azienda)
- `PrivacyDataTypeSeeder` deve essere eseguito prima (per avere ID_BASE con ID 1)
- `ProcessTaskPrivacyDataSeeder` deve essere eseguito prima (per avere le legal bases, incluso ID 3 "Obbligo Legale")

## File da Creare/Modificare

1. `database/seeders/OptOutManagementProcessSeeder.php` – il seeder principale
2. Aggiornamento di `database/seeders/DatabaseSeeder.php` per includere il nuovo seeder

## Compliance Checklist

- [ ] Interruzione immediata della proposta commerciale (Art. 21 GDPR – Diritto di Opposizione)
- [ ] Registrazione esito come "OPT-OUT" e NON come "Non Interessato" (evita falsi negativi)
- [ ] Sgancio immediato dalla coda di chiamata e dai ricontatti programmati
- [ ] Inserimento nella Suppression List globale (valida per tutte le campagne future)
- [ ] Base giuridica per la blacklist: Art. 6 par. 1 lett. c) (Obbligo Legale) — NON consenso
- [ ] Crittografia attiva per il dato in blacklist
- [ ] Tracciamento log temporale dell'opposizione
- [ ] Notifica al Committente/Utility tramite export SFTP di fine giornata
- [ ] Transazione DB per atomicità completa
- [ ] Idempotenza garantita su tutti i record
