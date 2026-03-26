<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use DutchCodingCompany\FilamentSocialite\Events\Login;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });

        Event::listen(function (Login $event) {
            $socialiteUser = $event->socialiteUser;
            $oauthUser = $event->oauthUser;

            if ($socialiteUser instanceof \App\Models\SocialiteUser) {
                // Mantiene aggiornati l'avatar e l'email presi dal provider ad ogni login
                $socialiteUser->update([
                    'email' => $oauthUser->getEmail(),
                    'avatar' => $oauthUser->getAvatar(),
                ]);
            }
        });
    }
}
