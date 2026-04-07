<?php

namespace App\Providers;

use App\Models\SocialiteUser;
use DutchCodingCompany\FilamentSocialite\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;
use Spatie\Activitylog\Models\Activity;

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
        /*
         * Activity::saving(function (Activity $activity) {
         *     $activity->properties = $activity->properties->put('ip_address', request()->ip());
         *     $activity->properties = $activity->properties->put('user_agent', request()->userAgent());
         * });
         */
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', Provider::class);
        });

        Event::listen(function (Login $event) {
            $socialiteUser = $event->socialiteUser;
            $oauthUser = $event->oauthUser;

            if ($socialiteUser instanceof SocialiteUser) {
                // Mantiene aggiornati l'avatar e l'email presi dal provider ad ogni login
                $socialiteUser->update([
                    'email' => $oauthUser->getEmail(),
                    'avatar' => $oauthUser->getAvatar(),
                ]);
            }
        });
    }
}
