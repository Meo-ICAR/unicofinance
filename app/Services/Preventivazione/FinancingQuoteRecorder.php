<?php

namespace App\Services\Preventivazione;

use App\Models\FinancingQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Unico\Core\Models\Fornitore;

/** Memorizza il preventivo e assegna il progressivo del produttore. */
class FinancingQuoteRecorder
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  array{min: float, max: float}  $quotation
     */
    public function store(Fornitore $fornitore, array $profile, array $quotation, string $status, ?int $praticaId, ?User $user): FinancingQuote
    {
        return DB::transaction(function () use ($fornitore, $profile, $quotation, $status, $praticaId, $user): FinancingQuote {
            $lastNumber = FinancingQuote::query()
                ->where('fornitore_id', $fornitore->getKey())
                ->orderByDesc('number')
                ->lockForUpdate()
                ->value('number');

            return FinancingQuote::query()->create([
                'fornitore_id' => $fornitore->getKey(),
                'number' => ((int) $lastNumber) + 1,
                'user_id' => $user?->getKey(),
                'disbursed_min' => $quotation['min'],
                'disbursed_max' => $quotation['max'],
                'status' => $status,
                'pratica_id' => $praticaId,
                ...$profile,
            ]);
        });
    }
}
