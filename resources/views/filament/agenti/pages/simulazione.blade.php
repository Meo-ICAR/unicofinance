<x-filament-panels::page>
    <form wire:submit="simulate">
        {{ $this->form }}
    </form>

    @if ($errorePreventivo)
        <x-filament::section heading="Preventivazione" class="mt-6">
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $errorePreventivo }}</p>
        </x-filament::section>
    @endif

    @if ($profilo && ($esito || $preventivatoreAssente))
        <x-filament::section :heading="$esito ? 'Esito preventivazione' : 'Simulazione'" class="mt-6">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tipo contratto</dt>
                    <dd class="font-medium">{{ $profilo['tipo_contratto'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tipo amministrazione</dt>
                    <dd class="font-medium">{{ $profilo['tipo_rapporto'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Durata</dt>
                    <dd class="font-medium">{{ $profilo['durata_etichetta'] }}</dd>
                </div>
                @if ($profilo['quota_cedibile'] !== null)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Quota cedibile</dt>
                        <dd class="font-medium">€ {{ number_format($profilo['quota_cedibile'], 2, ',', '.') }}</dd>
                    </div>
                @endif
                @if ($profilo['importo_richiesto'] !== null)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Importo richiesto</dt>
                        <dd class="font-medium">€ {{ number_format($profilo['importo_richiesto'], 2, ',', '.') }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Data decorrenza</dt>
                    <dd class="font-medium">{{ $profilo['data_decorrenza'] }}</dd>
                </div>
                @if ($esito)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Importo erogato</dt>
                        <dd class="font-medium">
                            da € {{ number_format($esito['min'], 2, ',', '.') }}
                            a € {{ number_format($esito['max'], 2, ',', '.') }}
                        </dd>
                    </div>
                @endif
            </dl>
            @if ($preventivatoreAssente)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    Preventivatore non configurato: il preventivo si salva senza importo erogato.
                </p>
            @endif
        </x-filament::section>

        @if ($fase === null)
            <x-filament::section heading="Salva il preventivo" class="mt-6">
                <x-filament::button type="button" wire:click="salvaPreventivo" icon="heroicon-o-bookmark">
                    Salva preventivo
                </x-filament::button>
            </x-filament::section>
        @else
            <x-filament::section heading="Pratica {{ $codicePratica }}" class="mt-6">
                <p class="text-sm">
                    Preventivo n. <strong>{{ $preventivoNumero }}</strong>
                    — stato <strong>{{ $statoPratica }}</strong>
                </p>

                @if ($fase === 'preventivo')
                    <div class="mt-4">
                        <x-filament::button type="button" color="success" wire:click="perfeziona" icon="heroicon-o-check-circle">
                            Perfeziona
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @endif

        @if ($fase === 'informativa')
            <x-filament::section heading="Informativa privacy" description="Prima dei dati personali: scarica il modulo, fallo firmare al cliente con firma olografa e carica la copia firmata (foto o PDF)." class="mt-6">
                <ul class="mb-4 space-y-2 text-sm">
                    @foreach ($informativaRichiesta as $documento)
                        <li>
                            <strong>{{ $documento['name'] }}</strong>
                            @if ($documento['url'])
                                —
                                <a href="{{ $documento['url'] }}" target="_blank" rel="noopener" class="text-primary-600 underline dark:text-primary-400">
                                    Scarica il modulo
                                </a>
                            @else
                                — modulo non disponibile per il download: contatta il back office.
                            @endif
                        </li>
                    @endforeach
                </ul>
                <form wire:submit="caricaInformativa" class="space-y-4">
                    {{ $this->informativaForm }}
                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                        Carica l'informativa firmata
                    </x-filament::button>
                </form>
            </x-filament::section>
        @endif

        @if ($avvisiInformativa !== [] && $fase !== 'informativa')
            <x-filament::section heading="Informativa da verificare" class="mt-6">
                <ul class="space-y-1 text-sm text-warning-600 dark:text-warning-400">
                    @foreach ($avvisiInformativa as $avviso)
                        <li>{{ $avviso }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @if ($fase === 'contatti')
            <x-filament::section heading="Contatti del cliente" description="Email e cellulare sono obbligatori per perfezionare la pratica." class="mt-6">
                <form wire:submit="confermaContatti" class="space-y-4">
                    {{ $this->contattiForm }}
                    <x-filament::button type="submit" color="success" icon="heroicon-o-check-circle">
                        Conferma e perfeziona
                    </x-filament::button>
                </form>
            </x-filament::section>
        @endif

        @if ($fase === 'perfezionare')
            {{ $this->documentiForm }}
            @if ($documentiMancanti !== [])
                <p class="mt-4 text-sm text-danger-600 dark:text-danger-400">
                    Mancano ancora: {{ implode(', ', $documentiMancanti) }}.
                </p>
            @endif
        @endif

        @if ($esitiDocumenti !== [])
            <x-filament::section heading="Documenti della pratica" class="mt-6">
                <ul class="space-y-2 text-sm">
                    @foreach ($esitiDocumenti as $esito)
                        <li>
                            <strong>{{ $esito['file'] }}</strong>
                            @if ($esito['tipo'])
                                — riconosciuto come {{ $esito['tipo'] }}
                            @else
                                — tipo non riconosciuto, da verificare
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @if ($fase === 'cliente')
            <x-filament::section heading="Cliente" class="mt-6">
                <form wire:submit="salvaCliente" class="space-y-4">
                    {{ $this->clienteForm }}
                    <x-filament::button type="submit">
                        Salva cliente
                    </x-filament::button>
                </form>
            </x-filament::section>
        @endif

        @if ($fase === 'cliente_salvato')
            <x-filament::section heading="Cliente salvato" class="mt-6">
                <p class="text-sm">Il cliente è collegato alla pratica come richiedente.</p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
