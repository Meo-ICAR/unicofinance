<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Models\Company;
use App\Models\SocialiteUser;
use App\Models\User;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // --- AGGIUNGI QUESTE RIGHE ---
            // Imposta il logo rettangolare (in alto a sinistra)
            ->brandLogo(asset('images/unicofinance_logo.png'))
            // Opzionale: imposta un'altezza fissa se ti sembra troppo grande o piccolo
            ->brandLogoHeight('3rem')
            // Imposta l'icona del browser (favicon)
            ->favicon(asset('images/unicofinance_icona.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            // Abilita la multi-tenancy
            ->tenant(Company::class, ownershipRelationship: 'company')
            // Menu per passare da un'azienda all'altra
            ->tenantMenu()
            ->databaseNotifications()  // <-- Aggiungi questo!
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->plugin(
                //   ActivityLogPlugin::make()
                //      ->label('Log')
                //     ->pluralLabel('Logs')
                //   ->navigationGroup('System'),
                //   ->cluster('System'),  // Optional: Group inside a cluster
                FilamentSocialitePlugin::make()
                    // (required) Add providers corresponding with providers in `config/services.php`.
                    ->providers([
                        // Create a provider 'gitlab' corresponding to the Socialite driver with the same name.
                        Provider::make('microsoft')
                            ->label('Microsoft')
                            ->icon('fab-microsoft')
                            ->color(Color::hex('#0078D4'))
                            ->outlined(true)
                            ->stateless(false),
                        Provider::make('google')
                            ->label('Google')
                            ->icon('fab-google')
                            ->color(Color::hex('#4285F4'))
                            ->outlined(true)
                            ->stateless(false),
                        //    ->scopes(['...'])
                        //    ->with(['...']),
                        //    ->scopes(['...'])
                        //    ->with(['...']),
                    ])
                    // (optional) Override the panel slug to be used in the oauth routes. Defaults to the panel's configured path.
                    // ->slug('admin')
                    // (optional) Enable/disable registration of new (socialite-) users.
                    ->registration(true)
                    ->socialiteUserModelClass(SocialiteUser::class)
                    ->createUserUsing(function (string $provider, SocialiteUserContract $oauthUser, FilamentSocialitePlugin $plugin) {
                        $email = $oauthUser->getEmail();

                        $user = User::create([
                            'name' => $oauthUser->getName() ?? $oauthUser->getNickname() ?? 'Utente Social',
                            'email' => $email,
                            'password' => Hash::make(Str::random(24)),
                            'is_approved' => false,
                        ]);

                        $emailParts = explode('@', $email);
                        if (count($emailParts) === 2) {
                            $domain = end($emailParts);
                            $company = Company::where('domain', strtolower($domain))->first();
                            if ($company) {
                                $user->companies()->attach($company->id);
                            }
                        }

                        return $user;
                    })
                    ->resolveUserUsing(function (string $provider, SocialiteUserContract $oauthUser, FilamentSocialitePlugin $plugin) {
                        return User::where('email', $oauthUser->getEmail())->first();
                    })
                // In this example, a login flow can only continue if there exists a user (Authenticatable) already.
                //  ->registration(fn(string $provider, SocialiteUserContract $oauthUser, ?Authenticatable $user) => (bool) $user)
                // (optional) Change the associated model class.
                //  ->userModelClass(\App\Models\User::class)
                // (optional) Change the associated socialite class (see below).
                //   ->socialiteUserModelClass(\App\Models\SocialiteUser::class)
            )
            ->widgets([
                //  AccountWidget::class,
                //  FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
