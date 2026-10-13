<x-filament-panels::page>
    <form wire:submit="simulate">
        {{ $this->form }}
    </form>

    @if ($errorePreventivo)
        <x-filament::section heading="Preventivazione" class="mt-6">
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $errorePreventivo }}</p>
        </x-filament::section>
    @endif

    @if ($profilo && $esito)
        <x-filament::section heading="Esito preventivazione" class="mt-6">
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
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Importo erogato</dt>
                    <dd class="font-medium">
                        da € {{ number_format($esito['min'], 2, ',', '.') }}
                        a € {{ number_format($esito['max'], 2, ',', '.') }}
                    </dd>
                </div>
            </dl>
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

        @if ($fase === 'perfezionare')
            <x-filament::section heading="Documenti del tipo prodotto" class="mt-6">
                @if ($documentiRichiesti === [])
                    <p class="text-sm">Nessun documento è definito nel task di questo tipo prodotto.</p>
                @else
                    <form wire:submit="caricaDocumenti" class="space-y-4">
                        {{ $this->uploadsForm }}
                        <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                            Carica documenti
                        </x-filament::button>
                    </form>
                @endif
                @if ($documentiMancanti !== [])
                    <p class="mt-4 text-sm text-danger-600 dark:text-danger-400">
                        Mancano ancora: {{ implode(', ', $documentiMancanti) }}.
                    </p>
                @endif
            </x-filament::section>
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
