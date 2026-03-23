<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;  // Aggiungi questo import
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;  // Aggiungi questo import
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;  // Aggiungi questo import

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
    }
}
