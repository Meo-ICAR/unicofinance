<?php

namespace Tests\Feature;

use App\Models\SocialiteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialiteLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /**
     * @return array<string, array{0: string}>
     */
    public static function oauthProviders(): array
    {
        return [
            'microsoft' => ['microsoft'],
            'google' => ['google'],
        ];
    }

    #[DataProvider('oauthProviders')]
    public function test_login_page_offers_the_provider(string $provider): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee(ucfirst($provider), false);
        $response->assertSee('/admin/oauth/'.$provider, false);
    }

    #[DataProvider('oauthProviders')]
    public function test_oauth_redirect_goes_to_the_provider(string $provider): void
    {
        Socialite::fake($provider);

        $response = $this->get('/admin/oauth/'.$provider);

        $response->assertRedirect('https://socialite.fake/'.$provider.'/authorize');
    }

    #[DataProvider('oauthProviders')]
    public function test_existing_staff_user_signs_in_and_links_the_provider(string $provider): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'email' => $provider.'.'.uniqid().'@example.test',
        ]);
        Socialite::fake($provider, OAuthUser::fake([
            'id' => $provider.'-account-1',
            'email' => $user->email,
            'name' => $user->name,
        ]));

        $response = $this->get('/admin/oauth/callback/'.$provider);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $linked = SocialiteUser::query()
            ->where('provider', $provider)
            ->where('provider_id', $provider.'-account-1')
            ->first();
        $this->assertNotNull($linked);
        $this->assertTrue($linked->user->is($user));
        $this->assertSame($user->email, $linked->email);
    }

    public function test_unknown_email_is_not_registered(): void
    {
        $email = 'sconosciuto.'.uniqid().'@example.test';
        Socialite::fake('google', OAuthUser::fake([
            'id' => 'google-unknown',
            'email' => $email,
        ]));

        $response = $this->get('/admin/oauth/callback/google');

        $response->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
        $this->assertNull(SocialiteUser::query()->where('provider_id', 'google-unknown')->first());
        $this->assertNull(User::query()->where('email', $email)->first());
    }
}
