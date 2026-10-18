# Richiesta documenti integrativi

Funzione AI non implementata. Assegnare l'esito Integrativo non genera un prompt e non invia un'email.

Chi risulta in carico è deciso da `Unico\Core\Documents\DocumentResponsibleResolver` al cambio di `documents.status`: produttore se `allows_direct_requests` della pratica (o, se nullo, del produttore) è falso; richiedente se è vero; datore o banca solo se `document_types.issuer_model` è `employer` o `institute`. L'email, se parte, è l'azione umana «Invia sollecito», con il template descritto in `email-richiesta-documenti-al-cliente.md`. Il comando `unico-core:documents-remind` non è schedulato in `routes/console.php`. Il menu Solleciti elenca solo i documenti in stato Richiesto.
