<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Punta Microsoft e Google al callback OAuth del pannello Filament corrente. */
class SetPanelSocialiteRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();

        if ($panel?->hasPlugin('filament-socialite')) {
            foreach (['microsoft', 'google'] as $provider) {
                config([
                    "services.{$provider}.redirect" => route(
                        'socialite.'.$panel->generateRouteName('oauth.callback'),
                        ['provider' => $provider],
                    ),
                ]);
            }
        }

        return $next($request);
    }
}
