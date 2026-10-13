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

        @if (! $preventivoDeciso)
            <x-filament::section heading="Salvare il preventivo o perfezionarlo?" class="mt-6">
                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="button" wire:click="salvaPreventivo" icon="heroicon-o-bookmark">
                        Salva preventivo
                    </x-filament::button>
                    <x-filament::button type="button" color="success" wire:click="perfeziona" icon="heroicon-o-check-circle">
                        Perfeziona
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <x-filament::section heading="Concluso" class="mt-6">
                <p class="text-sm">
                    Preventivo n.
                    <strong>{{ $preventivoNumero }}</strong>
                    @if ($codicePratica)
                        — pratica <strong>{{ $codicePratica }}</strong>
                    @else
                        salvato.
                    @endif
                </p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
