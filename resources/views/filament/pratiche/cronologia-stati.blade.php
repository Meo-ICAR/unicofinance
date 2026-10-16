<div class="space-y-4">
    @forelse ($storico as $voce)
        @php
            $daProduttore = $voce->source === 'agenti';
            $daVisionare = $voce->seen_at === null && ($daProduttore || ($voce->user_id !== null && $voce->user_id !== $istruttoreUserId));
        @endphp
        <div @class([
            'rounded-lg border p-3',
            'border-gray-200 dark:border-white/10' => ! $daVisionare,
            'border-warning-500 bg-warning-50 dark:bg-warning-400/10' => $daVisionare,
        ])>
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="font-medium">
                    {{ $voce->status_from ?: '—' }} → {{ $voce->status_to }}
                </span>
                <span class="text-gray-500">{{ $voce->changed_at?->format('d/m/Y H:i') }}</span>
            </div>
            <div class="mt-1 text-xs text-gray-500">
                @if ($daProduttore)
                    <strong>Produttore</strong> · {{ $voce->user?->name ?? '—' }}
                @else
                    Eseguito da: {{ $voce->employee?->name ?? $voce->user?->name ?? '—' }}
                @endif
                · origine: {{ $voce->source }}
                @if ($daVisionare)
                    · <strong>da visionare</strong>
                @elseif ($voce->seen_at)
                    · visto da {{ $voce->seenBy?->name ?? '—' }} il {{ $voce->seen_at->format('d/m/Y H:i') }}
                @endif
            </div>
            @if ($voce->notes)
                <p class="mt-2 text-sm">{{ $voce->notes }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Nessun cambio di stato registrato.</p>
    @endforelse
</div>
