<?php

namespace App\Http\Controllers;

use App\Services\Istruttoria\IstruttoreImpersonationService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class IstruttoriImpersonationController extends Controller
{
    public function leave(IstruttoreImpersonationService $impersonation): RedirectResponse
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
