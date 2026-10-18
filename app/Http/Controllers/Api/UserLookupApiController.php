<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLookupApiController extends Controller
{
    /**
     * unicobpm chiede se l'utente collegato ha anche un account qui, con accesso al pannello dell'istruttoria, per proporgli
     * il passaggio a unicofinance.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        return response()->json([
            'exists' => (bool) $user && $user->canAccessPanel(filament()->getPanel('admin')),
        ]);
    }
}
