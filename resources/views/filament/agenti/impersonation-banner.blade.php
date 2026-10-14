@php
    $impersonation = app(\App\Services\Agenti\AgentImpersonationService::class);
@endphp

@if ($impersonation->isImpersonating())
    <div class="fi-impersonation-banner flex items-center justify-between gap-x-4 bg-warning-600 px-4 py-2 text-sm text-white dark:bg-warning-500">
        <span>
            Stai visualizzando il portale agenti come
            <strong>{{ auth()->user()?->name }}</strong>.
        </span>

        <form method="POST" action="{{ route('agenti.impersonation.leave') }}">
            @csrf
            <button
                type="submit"
                class="rounded-md bg-white/15 px-3 py-1.5 font-medium hover:bg-white/25"
            >
                Torna all'admin
            </button>
        </form>
    </div>
@endif
