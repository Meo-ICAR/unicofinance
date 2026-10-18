<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <x-filament::section icon="heroicon-o-queue-list" icon-color="gray">
            <x-slot name="heading">
                La tua coda
            </x-slot>

            <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">{{ $this->contestoCoda() }}</p>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-chat-bubble-left-right" icon-color="primary">
            <x-slot name="heading">
                Chiedi all'assistente
            </x-slot>

            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Spiega il prossimo passo sulle pratiche che segui. Non cambia lo stato, non invia email e non scrive sul portale della banca.
            </p>

            <div class="space-y-3">
                @foreach ($this->messaggi() as $messaggio)
                    <div @class([
                        'rounded-lg p-4 text-sm',
                        'bg-gray-50 dark:bg-gray-800' => $messaggio['ruolo'] === 'assistant',
                        'bg-primary-50 dark:bg-primary-500/10' => $messaggio['ruolo'] === 'user',
                    ])>
                        <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $messaggio['ruolo'] === 'user' ? 'Tu' : 'Assistente' }}
                        </p>
                        <p class="whitespace-pre-wrap">{{ $messaggio['testo'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $this->form }}
            </div>

            <div wire:loading wire:target="send" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Sto lavorando...
            </div>

            @if ($answer)
                <div class="prose mt-4 max-w-none text-sm dark:prose-invert">
                    {!! \Illuminate\Support\Str::markdown($answer, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            @endif

            @if ($error)
                <div class="mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                    {{ $error }}
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
