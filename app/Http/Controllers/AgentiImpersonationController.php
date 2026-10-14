<?php

namespace App\Http\Controllers;

use App\Services\Agenti\AgentImpersonationService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AgentiImpersonationController extends Controller
{
    public function leave(AgentImpersonationService $impersonation): RedirectResponse
    {
        abort_unless($impersonation->isImpersonating(), Response::HTTP_FORBIDDEN);

        try {
            $url = $impersonation->leave();
        } catch (RuntimeException) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return redirect()->to($url);
    }
}
