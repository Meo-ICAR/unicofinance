<x-mail::message>
# Conto estintivo

Si trasmette il conteggio estintivo dell'impegno di terzi relativo alla pratica **{{ $pratica->codice_pratica }}** ({{ $pratica->cognome_cliente }} {{ $pratica->nome_cliente }}), verificato dall'istruttoria.

@if ($istituto)
Istituto che lo ha emesso: **{{ $istituto }}**.
@endif

Il documento è in allegato.
</x-mail::message>
