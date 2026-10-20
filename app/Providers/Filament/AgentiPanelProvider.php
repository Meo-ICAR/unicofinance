<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetPanelSocialiteRedirect;
use App\Models\SocialiteUser;
use App\Services\Agenti\AgentImpersonationService;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class AgentiPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->brandLogo(asset('brand/logos/unico-finance.jpg'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('brand/icons/unico-finance-64.png'))
            ->maxContentWidth(Width::Full)
            ->id('agenti')
            ->path('agenti')
            ->login()
            ->brandName('Portale agenti')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                NavigationGroup::make('Manuali')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Agenti/Resources'), for: 'App\Filament\Agenti\Resources')
            ->discoverPages(in: app_path('Filament/Agenti/Pages'), for: 'App\Filament\Agenti\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agenti/Widgets'), for: 'App\Filament\Agenti\Widgets')
            // Niente widget predefiniti di Filament (profilo e disconnessione): ci sono già nel menu dell'utente.
            ->widgets([])
            ->userMenuItems([
                Action::make('leaveImpersonation')
                    ->label('Torna all\'admin')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->url(fn (): string => route('agenti.impersonation.leave'))
                    ->postToUrl()
                    ->visible(fn (): bool => app(AgentImpersonationService::class)->isImpersonating()),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.agenti.impersonation-banner')->render(),
            )
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
                // Il redirect OAuth in services.* punta al pannello admin. Qui si sostituisce
                // con il callback di questo pannello, prima che Socialite costruisca il driver.
                SetPanelSocialiteRedirect::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                FilamentSocialitePlugin::make()
                    ->socialiteUserModelClass(SocialiteUser::class)
                    // Collega un account già presente (stessa email). Non crea utenti:
                    // il ruolo, e quindi l'accesso al pannello, si assegna a parte.
                    ->registration(fn (string $provider, SocialiteUserContract $oauthUser, ?Authenticatable $user): bool => $user !== null)
                    ->providers([
                        Provider::make('microsoft')
                            ->label('Microsoft')
                            ->icon('fab-microsoft')
                            ->color(Color::hex('#0078D4'))
                            ->outlined(false)
                            ->stateless(false),
                        Provider::make('google')
                            ->label('Google')
                            ->icon('fab-google')
                            ->color(Color::hex('#4285F4'))
                            ->outlined(false)
                            ->stateless(false),
                    ])
            );
    }
}
