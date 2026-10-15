<x-mail::message>
# Nuova pratica in istruttoria

@if ($simulazione)
**Messaggio di dimostrazione:** la pratica è un esempio e la firma OTP è simulata.

@endif
La pratica **{{ $pratica->codice_pratica }}** è stata inviata in istruttoria con il fascicolo firmato.

- **Cliente:** {{ trim($pratica->cognome_cliente.' '.$pratica->nome_cliente) }}
- **Prodotto:** {{ $prodotto ?? '—' }}
- **Banca:** {{ $pratica->denominazione_banca ?? '—' }}
- **Agente:** {{ $agente ?? '—' }}
@if ($plico)
- **Plico:** {{ $plico->name }} (allegato, e tra i documenti della pratica)
@endif

<x-mail::button :url="$url">
Apri la pratica
</x-mail::button>

La pratica compare nell'elenco «Da prendere in carico» della dashboard dell'istruttoria.
</x-mail::message>
