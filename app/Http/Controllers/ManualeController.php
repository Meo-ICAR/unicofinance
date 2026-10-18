<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

class ManualeController extends Controller
{
    public function utente(): Response
    {
        $user = $this->utenteAutenticato();

        abort_unless($user->isStaff() || $user->fornitore()->exists(), 403);

        return $this->pagina('manuale-utente.html');
    }

    public function tecnico(): Response
    {
        abort_unless($this->utenteAutenticato()->isStaff(), 403);

        return $this->pagina('manuale-tecnico.html');
    }

    private function pagina(string $file): Response
    {
        $contents = file_get_contents(resource_path('manuals/'.$file));

        abort_if($contents === false, 404);

        return response($contents, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function utenteAutenticato(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(redirect()->guest(route('filament.admin.auth.login')));
        }

        return $user;
    }
}
