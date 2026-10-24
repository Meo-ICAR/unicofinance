<?php

namespace App\Http\Controllers\Api;

use App\Filament\Agenti\Widgets\DocumentiDaSeguireAgente;
use App\Filament\Agenti\Widgets\PraticheInCorsoAgente;
use App\Filament\Agenti\Widgets\PreventiviDaPerfezionare;
use App\Filament\Agenti\Widgets\ProvvigioniAgente;
use App\Filament\Widgets\DaPrendereInCarico;
use App\Filament\Widgets\DocumentiDaSeguire;
use App\Filament\Widgets\EspostoInApi;
use App\Filament\Widgets\InFirma;
use App\Filament\Widgets\PraticheInCorso;
use App\Filament\Widgets\RisposteBancaInSospeso;
use App\Filament\Widgets\ScadenzeDocumenti;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;

/**
 * Le tabelle dei widget della dashboard come API per unicobpm (GET /api/widgets/{widget}), che decide a chi e quando inviarle.
 * Stessa query del widget, senza i filtri predefiniti: i widget degli istruttori sono globali o, con `employee_id`, di un solo
 * istruttore; quelli degli agenti richiedono `fornitori_id`. Qui non si invia nulla.
 */
class WidgetTabellaApiController extends Controller
{
    /**
     * @var array<string, class-string<EspostoInApi>>
     */
    public const WIDGET = [
        'da-prendere-in-carico' => DaPrendereInCarico::class,
        'pratiche-in-corso' => PraticheInCorso::class,
        'documenti-da-seguire' => DocumentiDaSeguire::class,
        'scadenze-documenti' => ScadenzeDocumenti::class,
        'in-attesa-banca' => RisposteBancaInSospeso::class,
        'in-firma' => InFirma::class,
        'agente-pratiche-in-corso' => PraticheInCorsoAgente::class,
        'agente-preventivi-da-perfezionare' => PreventiviDaPerfezionare::class,
        'agente-documenti-da-seguire' => DocumentiDaSeguireAgente::class,
        'agente-provvigioni' => ProvvigioniAgente::class,
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'widgets' => collect(self::WIDGET)->map(fn (string $widget): array => ['parametro' => $widget::parametroApi()])->all(),
        ]);
    }

    public function show(Request $request, string $widget): JsonResponse
    {
        $classe = self::WIDGET[$widget] ?? null;

        if ($classe === null) {
            return response()->json(['message' => "Widget '{$widget}' non disponibile."], 404);
        }

        $parametro = $classe::parametroApi();
        $dati = $request->validate([
            $parametro => [
                $parametro === 'fornitori_id' ? 'required' : 'nullable',
                'integer',
                Rule::exists($parametro === 'fornitori_id' ? Fornitore::class : Employee::class, 'id'),
            ],
        ]);
        $id = isset($dati[$parametro]) ? (int) $dati[$parametro] : null;

        $righe = $classe::queryApi($id)->get()->map(fn ($record): array => $classe::rigaApi($record))->values();

        return response()->json([
            'data' => $righe,
            'meta' => ['widget' => $widget, 'count' => $righe->count(), $parametro => $id],
        ]);
    }
}
