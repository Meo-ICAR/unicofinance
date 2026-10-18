<?php

namespace App\Models;

use App\Services\AvatarSocialite;
use DutchCodingCompany\FilamentSocialite\Models\Contracts\FilamentSocialiteUser as FilamentSocialiteUserContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use Unico\Core\Models\SocialiteUser as CoreSocialiteUser;

/** Account Microsoft o Google collegato a un utente del database condiviso. */
class SocialiteUser extends CoreSocialiteUser implements FilamentSocialiteUserContract
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getUser(): Authenticatable
    {
        $user = $this->user;

        assert($user instanceof Authenticatable);

        return $user;
    }

    public static function findForProvider(string $provider, SocialiteUserContract $oauthUser): ?self
    {
        $collegato = self::query()
            ->where('provider', $provider)
            ->where('provider_id', $oauthUser->getId())
            ->first();

        // A ogni accesso l'avatar si allinea a quello del provider.
        if ($collegato !== null && filled($oauthUser->getAvatar())) {
            $collegato->forceFill(['avatar' => app(AvatarSocialite::class)->indirizzo($oauthUser->getAvatar(), $collegato->user_id)])->save();
        }

        return $collegato;
    }

    public static function createForProvider(string $provider, SocialiteUserContract $oauthUser, Authenticatable $user): self
    {
        return self::query()->create([
            'user_id' => $user->getKey(),
            'provider' => $provider,
            'provider_id' => $oauthUser->getId(),
            'email' => $oauthUser->getEmail(),
            'avatar' => app(AvatarSocialite::class)->indirizzo($oauthUser->getAvatar(), $user->getKey()),
            'token' => $oauthUser instanceof OAuthTwoUser ? $oauthUser->token : null,
            'refresh_token' => $oauthUser instanceof OAuthTwoUser ? $oauthUser->refreshToken : null,
            'expires_at' => $oauthUser instanceof OAuthTwoUser && $oauthUser->expiresIn
                ? now()->addSeconds((int) $oauthUser->expiresIn)
                : null,
        ]);
    }
}
