# Email di richiesta documenti al cliente

Funzione implementata. Non è un prompt di modello: è il template email di `Unico\Core\Documents\PracticeDocumentRequestMailer`.

- Quando: azione «Chiedi i documenti al cliente per email» in `App\Filament\Agenti\Pages\Simulazione` (severity `regular`) e azione di gruppo «Invia sollecito» in `App\Filament\Resources\Solleciti\SollecitoResource` (severity `warning`, destinatario = soggetto in carico, istruttore in copia).
- Template: codice configurato in `unico-core.practice_intake.request_template`, testi di fallback e di seeder in `Unico\Core\Seeders\PerfezionamentoDocumentiSeeder` (`EMAIL_RICHIESTA`). Segnaposto: `{codice_pratica}`, `{cliente_nome}`, `{agente_nome}`, `{elenco_documenti}`.
- Corpo di default se il template manca (`PracticeDocumentRequestMailer::defaultBody`):

```
Gentile {cliente_nome},

per proseguire con la pratica {codice_pratica} ci servono ancora i seguenti documenti:

{elenco_documenti}

Rispondi a questa email allegando i documenti (foto nitide o PDF). Lascia il codice pratica nell'oggetto.

Grazie,
{agente_nome}
```
