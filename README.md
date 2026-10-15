# unicofinance

Gestionale per **mediatori creditizi**: raccolta della documentazione, istruttoria e inserimento delle pratiche di finanziamento nei portali delle banche. Non è un'app per banche o finanziarie: la valutazione del credito e la perizia le fa la banca. L'app tiene solo i dati che servono alla prima fattibilità o al portale della banca; il resto sta nei documenti allegati.

## Il flusso di una pratica

1. **Raccolta (agente / mediatore).** L'agente apre la pratica, inserisce email e cellulare del cliente e carica i documenti della check list del prodotto. I documenti d'identità vengono letti dall'AI e compilano l'anagrafica.
2. **Perfezionamento.** L'informativa privacy viene firmata; poi si stampa la modulistica del prodotto e la si fa firmare con OTP (Yousign).
3. **Istruttoria (back office).** Controlla la pratica e la inserisce sul portale della banca, chiedendo al più i documenti integrativi richiesti dalla banca.
4. **Esito.** Lo stato della pratica si aggiorna man mano che la banca risponde sul portale: invio, approvazione, erogazione o rifiuto.

**La documentazione è il fulcro.** In ogni risorsa che ha documenti, la scheda Documenti è la prima. Un test (`DocumentazionePrimaTest`) controlla che resti così.

- **Esito dell'istruttoria.** Dalla scheda Documenti di una pratica, su un documento o su una selezione, si assegna l'esito: OK, Respinto, Anomalo, N/A o Integrativo (richiesto in più dalla banca). Per respinto, anomalo e integrativo la nota è obbligatoria; restano registrati chi ha deciso e quando. Anomalo e integrativo continuano a essere sollecitati.
- **Completezza.** L'elenco pratiche mostra la colonna «Documenti» (obbligatori presenti sul totale), calcolata da `CompletezzaDocumentale`: check list del prodotto, per il gruppo di tipologia lavorativa del richiedente, più il fascicolo da firmare, filtrati per banca. Un requisito è soddisfatto da un documento caricato, OK, provvisorio o N/A.
- **Stati che richiedono la documentazione completa** (`config/istruttoria.php`: FASCICOLO COMPLETO, INVIO IN ISTRUTTORIA, Caricata Banca). Per ora il cambio di stato **non è bloccante**: la pratica passa e compare un avviso con i documenti che mancano, così si può recuperare lo storico. Con `ISTRUTTORIA_DOCUMENTAZIONE_BLOCCANTE=true` il cambio viene rifiutato.

## Architettura

- **Laravel 13 + Filament 5.** Si usano solo form, tabelle e azioni Filament, senza componenti Livewire scritti a mano.
- **[unico-core](https://github.com/Meo-ICAR/unico-core)** (`../unico-core`, dipendenza `meo-icar/unico-core`). È il pacchetto condiviso da tutte le app: anagrafiche, pratiche, documenti, firma, mail, task e check list. **La gestione documentale sta tutta nel core.** Le funzioni utili a più app vanno lì, non in unicofinance.
- **Due database:**
  - `mysql` (`unicofinance`): tabelle proprie dell'app, cioè utenti, listini provvigionali, blacklist istituto–produttore e `client_relations`.
  - `core` (`unico_core_test`, condiviso tra le app): clienti, pratiche, documenti, stati, task, istituti (`clientis`) e produttori (`fornitoris`). I modelli del core usano `UsesCoreConnection`.
- **Relazioni tra i due database:** una relazione eredita la connessione del modello da cui parte. Un modello di unicofinance raggiunto da un modello del core deve quindi ridefinire `getConnectionName()`, come fa `App\Models\ClientRelation`.

### Pannelli

| Pannello | Percorso | Per chi | Risorse |
|---|---|---|---|
| `admin` | `/admin` | back office e istruttoria | Pratiche, Clienti, Istituti, Produttori, Listini provvigionali |
| `agenti` | `/agenti` | agenti (anche impersonati dal back office) | le proprie Pratiche ("Perfeziona") e i Clienti di quelle pratiche |

**Dashboard.**
- *Impersonifica istruttore:* da «Configurazione → Istruttori» un amministratore entra nel pannello come uno degli istruttori (con utente collegato) per vederne la dashboard e le pratiche; un banner permette di tornare al proprio utente.
- *Admin (istruttori):* Da prendere in carico (pratiche senza `employee_id`, con presa in carico), Pratiche in corso, Documenti da seguire, Documenti in scadenza, In attesa della banca, Documenti in firma. I filtri sono preimpostati sull'istruttore collegato all'utente (`employees.user_id`); «Da prendere in carico» mostra di default solo le pratiche inserite negli ultimi 30 giorni.
- *Agenti:* Preventivi da perfezionare (si riprendono nella Simulazione con `?preventivo=ID`, oppure si eliminano anche in blocco insieme alla pratica in stato PREVENTIVO), Le mie pratiche in corso, Documenti da consegnare.
- *Agenti, Scadenziario:* i documenti delle pratiche su cui l'agente matura provvigioni, sue o dei suoi collaboratori (escluse declinate e sospese). Di default solo quelli alla firma (tipo da firmare e non ancora firmato); filtri per pratiche dirette o dei collaboratori e per scaduti o in scadenza.
- **Una pratica presa in carico da un istruttore (`employee_id`) non è più modificabile dall'agente**: sparisce l'azione «Perfeziona» e la pagina di modifica risponde 403.

## Funzioni principali

### Pratica
- **Richiedente** su 4 colonne. Il codice fiscale o la partita IVA vengono controllati con le routine di unico-core (`App\Support\ControlloRichiedente`). Accanto al campo ci sono il link per aprire o creare la scheda del cliente e l'azione Openapi per i dati societari.
- **Prodotto richiesto:**
  - impegno di terzi, tipo prodotto e banca, con il link al portale della banca (`PortaleBanca`, che usa `portal_url` o il sito web dell'istituto);
  - la rata per cessione e delega, oppure importo e durata per gli altri prodotti;
  - numero pratica, **codice pratica banca** e data della richiesta.
- **Cambio di stato:**
  - Chiede sempre una motivazione e scrive lo storico in `pratica_status_history`.
  - Si fa dal form (trait `RegistraCambioStato`) oppure dall'elenco: badge dello stato, azione sulla riga o azione sulle righe selezionate. Entrambe le strade passano dal servizio `CambioStatoPratica`.
  - Se il nuovo stato ha il flag corrispondente, la data della pratica si valorizza con oggi, solo se è vuota:
    - `pratica_stati.is_rejected` → `rejected_at`;
    - `is_sended` → `sended_at`;
    - `is_approved` → `approved_at`;
    - `is_erogated` → `erogated_at`.
- **Elenco:**
  - filtri predefiniti che escludono le respinte (`rejected_at` valorizzato) e le sospese (stati con `is_suspended`);
  - filtri per banca, agente e stato;
  - ricerca anche per codice pratica banca.
- **Mutui** ("Mutuo", "Mutuo ristrutturazione", "IPOTECARIO"; vedi `App\Support\ProdottoMutuo`):
  - Cointestatari e garanti, con ruolo e quota %. Il cliente viene creato dal codice fiscale se non esiste.
  - Dati dell'immobile in `pratica_immobili`: tipologia, destinazione, finalità, valore e prezzo, importo dei lavori, con l'LTV calcolato. L'indirizzo è un `Address` di tipo "Località".

### Cliente
- **Primo riquadro:**
  - cognome o ragione sociale, persona fisica, nome e codice fiscale o partita IVA;
  - "persona fisica" resta bloccato se il cliente ha pratiche di cessione, delega o prestito;
  - credit score Openapi (`GET /IT-creditscore-top/{CF}`), per ora **simulato**: c'è solo una notifica e la chiamata viene registrata in `api_calls`.
- **Persone giuridiche:**
  - **Soci e titolari effettivi** in `client_relations`, con ruolo (legale rappresentante, amministratore, socio, titolare effettivo, referente, garante) e quota. Il PEP si verifica sul cliente collegato.
  - La scheda Verifiche (AML, KYC, consensi) vale anche per le società, senza il flag PEP.
  - **Contatti** (`ContattiSocieta`): le comunicazioni vanno al referente, se manca al legale rappresentante, se manca alla società. La firma OTP spetta sempre al legale rappresentante: se non ha il cellulare, la pratica mostra un avviso.

### Listini provvigionali della rete
I listini (`listini_provvigionali`) hanno una percentuale per **voce di compenso** (`listini_provvigionali_voci`): standard e special per cessione e delega; banca, mediazione e polizza per il mutuo; banca per il prestito; consulenza per l'aziendale; premio e brokeraggio per le polizze. `ListiniReteSeeder` li ricava dalla presentazione «Struttura Rete distributiva»: livelli Consulente del credito, Special e Premium (pratiche dirette) e, come coordinatore, le over provvigionali del Premium con coordinamento e del Supervisore. Il Supervisore sulle pratiche dirette ha la tabella del Premium. I listini che non sono nella presentazione (sede, lead, rete, TFS) restano con il valore di prova.

### Istituti e produttori
- **Istituto:**
  - portale per l'inserimento delle pratiche (`portal_url`);
  - blacklist dei produttori;
  - **documenti richiesti** specifici dell'istituto, per prodotto e fase (vedi sotto).
- **Produttore:**
  - listini provvigionali, diretti o da coordinatore, per tipo prodotto;
  - istituti che non lo vogliono;
  - sedi.

### Documenti richiesti: prodotto, fase e banca
- Le check list stanno nei **task** del core. Il trigger `tipo_prodotto` = nome del prodotto dice per quale prodotto vale il task. I tipi documento richiesti sono in `document_requirements`.
- Le fasi sono due:
  - **check list**: documenti da chiedere al cliente, un task per gruppo di tipologia lavorativa (privati, pubblici, parapubblici, pensionati, autonomi) o un task unico per i prodotti aziendali;
  - **FASCICOLO COMPLETO**: modulistica da stampare e firmare.
- Un tipo documento con `clienti_id` vale solo per le pratiche di quella banca. Dall'istituto si creano e si collegano ai task con `BankDocumentRequirements`.
- I documenti multipli sono numerati (Busta paga 1-3, CU 1-2) e condividono un `codegroup`.
- Le check list si rigenerano con `ChecklistPerfezionamentoSeeder` del core, che si può rieseguire.

## Sviluppo

```sh
composer install
cp .env.example .env    # DB_* per unicofinance, CORE_DB_* per unico_core_test
php artisan key:generate
php artisan migrate     # solo le tabelle di unicofinance
```

- **Migrazioni del core.** Stanno in `../unico-core/database/migrations`. Si applicano a `unico_core_test` con `--database=core`, usando come tabella delle migrazioni quella del core (`config('unico-core.migrations_table')`), una migrazione alla volta con `--path`.
- **Seed del core.** Contengono solo tabelle senza dati personali (tipi documento, stati, task...). **Mai dati di clienti, produttori, pratiche o istituti nei seeder.**
- **Test.** `php artisan test`. I test dell'app usano sqlite per il database di default e la connessione `core` reale dentro una transazione (`$connectionsToTransact = [null, 'core']`). **Non eseguire la suite del core su `unico_core_test`:** svuota il database condiviso. I test del core girano solo su `unico_core_phpunit`.
- **Stile.** `vendor/bin/pint --dirty` prima di ogni commit.
- **Backup.** Nel periodo di sviluppo `../backups3` fa un dump orario di `unico_core_test` e `unicofinance` (`backup:hourly`, conservazione 72 ore), più il backup notturno.

### Comandi utili del core
- `unico-core:pdf-slots`: posizioni di firma nei moduli PDF.
- `signature:sync`, `signature:check`: firme Yousign.
- `unico-core:mail-fetch`: risposte email alle richieste di documenti.
- `unico-core:documents-remind`, `unico-core:documents-schedule-sync`: scadenze e solleciti.
- `unico-core:seed-export`: esporta in JSON le tabelle di catalogo per i seeder.

## Integrazioni

| Servizio | Uso | Configurazione |
|---|---|---|
| Openapi | dati societari e soci (attivo); credit score (simulato) | `OPENAPI_TOKEN`, `config/unico-core.php` (`openapi.*`) |
| Cerved | score del richiedente | `services.cerved` |
| Sanctions (AML) | verifica PEP e sanzioni | `services.aml`, `AML_MIN_SCORE` |
| Yousign | firma con OTP | configurazione firma di unico-core |
| Mediafacile | preventivi | `App\Services\Preventivazione` |

Ogni chiamata a un servizio esterno viene registrata in `api_calls` (`ApiCallLogger`), con il costo da `config/services.php`.
