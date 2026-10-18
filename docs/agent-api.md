# API in ingresso per unicoagent

Prefisso `/api/agente/v1`. Si abilita un client con `php artisan agent-api:client unicoagent`: token e segreto si stampano una sola volta e vanno nella configurazione CRM di unicoagent.

Ogni richiesta porta `Authorization: Bearer <token>`, `X-Timestamp` (unix, valido 5 minuti) e `X-Signature` = esadecimale di HMAC-SHA256 del segreto su `timestamp.METODO.percorso.sha256-del-contenuto`. Il contenuto firmato è il corpo JSON; nei caricamenti è il file, e lo stesso hash va in `X-Content-Sha256`.

| Metodo | Percorso | Esito |
|---|---|---|
| POST | `/richieste` | Crea cliente e pratica. Idempotente per `riferimento`. Risposta `codice_pratica` = `WA-` + riferimento. `409 cliente_di_altro_agente`, `422 agente_sconosciuto` o `dati_incompleti`. |
| POST | `/richieste/{riferimento}/documenti` | Multipart `file`, `tipo`, `id_origine`, `analisi`. `422 hash_mancante` o `hash_non_coincide`. Un file già ricevuto (stesso hash o stessa origine) non si duplica. |
| GET | `/richieste/{riferimento}/moduli` | `{moduli: [{id, nome}]}` |
| GET | `/richieste/{riferimento}/moduli/{modulo}/template` | PDF vuoto, intestazione `X-Content-Sha256`. `404 modulo_non_trovato`, `404 template_non_disponibile`. |
| POST | `/richieste/{riferimento}/moduli/{modulo}/compilato` | Compila il modulo e risponde `{id}` del documento. |
| POST | `/richieste/{riferimento}/documenti/{id}/firma` | Chiede la firma OTP. Risposta `{stato}`: `pending`, `sent`, `signed`, `declined`, `expired`. |
| GET | `/richieste/{riferimento}/documenti/{id}/firma` | Stato della firma. |
| GET | `/richieste/{riferimento}/documenti/{id}/file` | PDF firmato, se c'è, altrimenti l'originale. |
