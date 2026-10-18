<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bpm\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ModelFieldValueApiController extends Controller
{
    /**
     * Scrive un campo su un record (modello, id, campo, valore): è la scrittura di fine processo di unicobpm
     * (`completion_write_field`, es. `stipulated_at` del produttore). Un campo lookup si risolve prima nel valore da scrivere.
     * Lo stato della pratica non si scrive da qui: cambia solo con CambioStatoPratica, che tiene storico e date.
     */
    public function update(Request $request, string $model, string $id, ModelFieldIntrospector $introspector): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string'],
            'value' => ['present', 'nullable'],
        ]);

        try {
            $modelClass = $introspector->resolveModelClass($model);
            $columnNames = collect($introspector->columns($model))->pluck('name');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        if (! $columnNames->contains($validated['field'])) {
            return response()->json(['message' => "Campo '{$validated['field']}' inesistente su {$model}."], 422);
        }

        if (! $introspector->isWritable($model, $validated['field'])) {
            return response()->json(['message' => "Il campo '{$validated['field']}' di {$model} non si scrive da API."], 422);
        }

        $record = $modelClass::query()->find($id);

        if (! $record) {
            return response()->json(['message' => 'Record non trovato.'], 404);
        }

        try {
            $resolvedValue = $introspector->resolveFieldValue($model, $validated['field'], $validated['value']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $record->forceFill([$validated['field'] => $resolvedValue])->save();

        return response()->json([
            'field' => $validated['field'],
            'value_stored' => $resolvedValue,
        ]);
    }
}
