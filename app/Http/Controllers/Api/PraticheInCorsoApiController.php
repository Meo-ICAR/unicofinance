<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PraticaInCorsoResource;
use App\Models\Pratica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Unico\Core\Models\Employee;

/**
 * Tabella delle pratiche in corso (`Pratica::inCorso()`) per unicobpm, che decide a chi e quando inviarla: tutte le pratiche,
 * oppure con `employee_id` solo quelle dell'istruttore indicato. Qui non si invia nulla.
 */
class PraticheInCorsoApiController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $dati = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
        ]);

        $istruttore = isset($dati['employee_id']) ? (int) $dati['employee_id'] : null;

        $pratiche = Pratica::query()
            ->inCorso()
            ->when($istruttore, fn ($query, int $id) => $query->where('employee_id', $id))
            ->with(['praticaStato', 'tipoprodotto', 'fornitori', 'clienti', 'employee'])
            ->orderBy('data_inserimento_pratica')
            ->orderBy('id')
            ->get();

        return PraticaInCorsoResource::collection($pratiche)->additional([
            'meta' => ['count' => $pratiche->count(), 'employee_id' => $istruttore],
        ]);
    }
}
