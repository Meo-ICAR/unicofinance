<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bpm\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ModelFieldsApiController extends Controller
{
    /**
     * Colonne (con i commenti reali di MySQL) e relazioni lookup di un modello: unicobpm le usa per la select dei campi da
     * includere o escludere quando si configura un processo.
     */
    public function show(string $model, ModelFieldIntrospector $introspector): JsonResponse
    {
        try {
            $columns = $introspector->columns($model);
            $lookups = $introspector->lookups($model);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'model' => $model,
            'columns' => $columns,
            'lookups' => $lookups,
        ]);
    }
}
