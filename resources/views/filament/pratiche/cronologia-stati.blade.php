<div class="space-y-4">
    @forelse ($storico as $voce)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="font-medium">
                    {{ $voce->status_from ?: '—' }} → {{ $voce->status_to }}
                </span>
                <span class="text-gray-500">{{ $voce->changed_at?->format('d/m/Y H:i') }}</span>
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Istruttore: {{ $voce->employee?->name ?? $voce->user?->name ?? '—' }}
                · origine: {{ $voce->source }}
            </div>
            @if ($voce->notes)
                <p class="mt-2 text-sm">{{ $voce->notes }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Nessun cambio di stato registrato.</p>
    @endforelse
</div>
