<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SocialAccount;
use App\Models\SocialAuthAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_unconfigured_error_when_credentials_missing(): void
    {
        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);

        $response = $this->getJson('/api/v1/auth/social/google/redirect');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'status' => 'unconfigured',
                'provider' => 'google',
            ]);
    }

    public function test_mobile_redirect_stores_hashed_state_and_uses_backend_callback(): void
    {
        Config::set('services.google.client_id', 'google-client-id');
        Config::set('services.google.client_secret', 'google-client-secret');
        Config::set('services.google.redirect', 'https://aqua-clayne-production.up.railway.app/api/v1/auth/social/google/callback');

        $response = $this->postJson('/api/v1/auth/social/google/mobile/redirect');
        $response->assertOk()
            ->assertJsonPath('data.provider', 'google');

        $redirectUrl = $response->json('data.redirect_url');
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? '';

        $this->assertMatchesRegularExpression('/\Aacm_[A-Za-z0-9_-]{43}\z/', $state);
        $this->assertSame(
            'https://aqua-clayne-production.up.railway.app/api/v1/auth/social/google/callback',
            $query['redirect_uri'] ?? null
        );
        $this->assertDatabaseHas('social_auth_attempts', [
            'flow' => 'mobile',
            'provider' => 'google',
            'state_hash' => hash('sha256', $state),
            'code_hash' => null,
        ]);
        $this->assertDatabaseMissing('social_auth_attempts', ['state_hash' => $state]);
    }

    public function test_web_redirect_keeps_a_separate_validated_state(): void
    {
        Config::set('services.google.client_id', 'google-client-id');
        Config::set('services.google.client_secret', 'google-client-secret');
        Config::set('services.google.redirect', 'https://aqua-clayne-production.up.railway.app/api/v1/auth/social/google/callback');

        $response = $this->get('/auth/google/redirect');
        $response->assertRedirect();

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $state = $query['state'] ?? '';

        $this->assertMatchesRegularExpression('/\Aacw_[A-Za-z0-9_-]{43}\z/', $state);
        $this->assertDatabaseHas('social_auth_attempts', [
            'flow' => 'web',
            'provider' => 'google',
            'state_hash' => hash('sha256', $state),
        ]);
    }

    public function test_mobile_callback_redirects_with_only_a_hashed_one_time_code(): void
    {
        Config::set('services.google.client_id', 'google-client-id');
        Config::set('services.google.client_secret', 'google-client-secret');

        $user = User::create([
            'name' => 'OAuth Customer',
            'username' => 'oauth.customer',
            'email' => 'oauth.customer@example.com',
            'password_hash' => Hash::make('Secret123!'),
            'role' => 'customer',
            'status' => 'active',
        ]);
        $user->customer()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google_oauth_customer',
            'provider_email' => $user->email,
        ]);

        $state = 'acm_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $attempt = SocialAuthAttempt::create([
            'flow' => 'mobile',
            'provider' => 'google',
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        $socialUser = (new SocialiteUser())->map([
            'id' => 'google_oauth_customer',
            'name' => 'OAuth Customer',
            'email' => $user->email,
        ]);
        $provider = \Mockery::mock();
        $provider->shouldReceive('scopes')->once()->andReturnSelf();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($socialUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/api/v1/auth/social/google/callback?' . http_build_query([
            'state' => $state,
            'code' => 'provider-authorization-code',
        ]));

        $response->assertOk()
            ->assertSee('aquaclayne://auth/callback?status=authenticated', false)
            ->assertDontSee('token=', false)
            ->assertDontSee('provider-authorization-code', false);

        $this->assertSame(
            1,
            preg_match('/status=authenticated&amp;code=([A-Za-z0-9_-]{43})/', $response->getContent(), $matches)
        );
        $this->assertSame(hash('sha256', $matches[1]), $attempt->fresh()->code_hash);
        $this->assertNotSame($matches[1], $attempt->fresh()->code_hash);
        $this->assertNotNull($attempt->fresh()->state_used_at);
    }

    public function test_unsupported_provider_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/social/twitter/redirect');
        $response->assertStatus(422);
    }

    public function test_social_registration_creates_new_customer_user_and_token(): void
    {
        $response = $this->postJson('/api/v1/auth/social/google/token', [
            'mock_user' => [
                'id' => 'google_1234567890',
                'name' => 'Maria Santos',
                'email' => 'maria.santos@gmail.com',
                'avatar' => 'https://example.com/avatar.jpg',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'is_new_user' => true,
                    'user' => [
                        'name' => 'Maria Santos',
                        'email' => 'maria.santos@gmail.com',
                        'role' => 'customer',
                        'status' => 'active',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'maria.santos@gmail.com',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $user = User::where('email', 'maria.santos@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google_1234567890',
        ]);
    }

    public function test_social_login_with_existing_social_account_succeeds(): void
    {
        $user = User::create([
            'name' => 'Juan Dela Cruz',
            'username' => 'juan.delacruz',
            'email' => 'juan@gmail.com',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $user->customer()->create();

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_user_id' => 'fb_9988776655',
            'provider_email' => 'juan@gmail.com',
            'avatar_url' => 'https://graph.facebook.com/picture',
        ]);

        $response = $this->postJson('/api/v1/auth/social/facebook/token', [
            'mock_user' => [
                'id' => 'fb_9988776655',
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@gmail.com',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_new_user' => false,
                    'user' => [
                        'id' => $user->id,
                        'email' => 'juan@gmail.com',
                    ],
                ],
            ]);
    }

    public function test_social_login_with_inactive_user_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'Inactive User',
            'username' => 'inactive.user',
            'email' => 'inactive@gmail.com',
            'role' => 'customer',
            'status' => 'inactive',
        ]);
        $user->customer()->create();

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google_inactive_123',
            'provider_email' => 'inactive@gmail.com',
        ]);

        $response = $this->postJson('/api/v1/auth/social/google/token', [
            'mock_user' => [
                'id' => 'google_inactive_123',
                'name' => 'Inactive User',
                'email' => 'inactive@gmail.com',
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_existing_email_requires_secure_account_linking(): void
    {
        $existing = User::create([
            'name' => 'Existing Customer',
            'username' => 'existing.cust',
            'email' => 'existing@gmail.com',
            'password_hash' => Hash::make('Secret123!'),
            'role' => 'customer',
            'status' => 'active',
        ]);
        $existing->customer()->create();

        // Attempt social sign in with the same email from Google
        $response = $this->postJson('/api/v1/auth/social/google/token', [
            'mock_user' => [
                'id' => 'google_new_id_456',
                'name' => 'Existing Customer',
                'email' => 'existing@gmail.com',
            ],
        ]);

        // Must NOT automatically merge or log in! Must return 409 needs_linking
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'status' => 'needs_linking',
                'data' => [
                    'needs_linking' => true,
                    'provider' => 'google',
                    'email' => 'existing@gmail.com',
                ],
            ]);

        $linkToken = $response->json('data.link_token');
        $this->assertNotEmpty($linkToken);

        // Verify wrong password fails
        $wrongPassResponse = $this->postJson('/api/v1/auth/social/link', [
            'link_token' => $linkToken,
            'password' => 'WrongPassword!',
        ]);
        $wrongPassResponse->assertStatus(422);

        // Verify correct password links account
        $correctPassResponse = $this->postJson('/api/v1/auth/social/link', [
            'link_token' => $linkToken,
            'password' => 'Secret123!',
        ]);

        $correctPassResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'linked' => true,
                    'user' => [
                        'id' => $existing->id,
                        'email' => 'existing@gmail.com',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_user_id' => 'google_new_id_456',
        ]);
    }

    public function test_facebook_without_email_creates_customer_profile(): void
    {
        $response = $this->postJson('/api/v1/auth/social/facebook/token', [
            'mock_user' => [
                'id' => 'fb_phone_only_user_777',
                'name' => 'Facebook Phone User',
                'email' => null, // Facebook accounts registered with phone only
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'is_new_user' => true,
                    'needs_profile_completion' => true,
                ],
            ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'facebook',
            'provider_user_id' => 'fb_phone_only_user_777',
        ]);
    }

    public function test_mobile_social_code_exchange_returns_sanctum_token_once(): void
    {
        $user = User::create([
            'name' => 'Mobile Customer',
            'username' => 'mobile.customer',
            'email' => 'mobile.customer@example.com',
            'password_hash' => Hash::make('Secret123!'),
            'role' => 'customer',
            'status' => 'active',
        ]);
        $user->customer()->create();

        $code = str_repeat('a', 43);
        $attempt = SocialAuthAttempt::create([
            'flow' => 'mobile',
            'provider' => 'google',
            'state_hash' => hash('sha256', 'state_' . str_repeat('b', 32)),
            'code_hash' => hash('sha256', $code),
            'user_id' => $user->id,
            'expires_at' => now()->addSeconds(60),
            'state_used_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/social/exchange', ['code' => $code]);
        $response->assertOk()
            ->assertJsonPath('message', 'Authentication successful.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotSame($code, $response->json('data.token'));
        $this->assertNotNull($attempt->fresh()->used_at);

        $this->postJson('/api/v1/auth/social/exchange', ['code' => $code])
            ->assertStatus(422);
    }

    public function test_mobile_social_code_exchange_rejects_expired_and_invalid_codes(): void
    {
        $user = User::create([
            'name' => 'Expired Customer',
            'username' => 'expired.customer',
            'email' => 'expired.customer@example.com',
            'password_hash' => Hash::make('Secret123!'),
            'role' => 'customer',
            'status' => 'active',
        ]);
        $user->customer()->create();

        $expiredCode = str_repeat('c', 43);
        SocialAuthAttempt::create([
            'flow' => 'mobile',
            'provider' => 'facebook',
            'state_hash' => hash('sha256', 'state_' . str_repeat('d', 32)),
            'code_hash' => hash('sha256', $expiredCode),
            'user_id' => $user->id,
            'expires_at' => now()->subSecond(),
            'state_used_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/auth/social/exchange', ['code' => $expiredCode])
            ->assertStatus(422);
        $this->postJson('/api/v1/auth/social/exchange', ['code' => str_repeat('e', 43)])
            ->assertStatus(422);
    }

    public function test_invalid_mobile_callback_state_does_not_redirect_to_web_dashboard(): void
    {
        $response = $this->get('/api/v1/auth/social/google/callback?state=acm_invalid');

        $response->assertOk()
            ->assertSee('This sign-in attempt is invalid or has expired')
            ->assertDontSee('customer.index');
    }

    public function test_unlink_account_prevents_lockout_if_no_password(): void
    {
        $user = User::create([
            'name' => 'Social Only User',
            'username' => 'social.only',
            'email' => 'socialonly@gmail.com',
            'password_hash' => null, // No password set
            'role' => 'customer',
            'status' => 'active',
        ]);
        $user->customer()->create();

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google_only_123',
            'provider_email' => 'socialonly@gmail.com',
        ]);

        $token = $user->createToken('api')->plainTextToken;

        // Attempting to delete the only login method must be rejected
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/social/google');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot disconnect your only login method. Please set an account password first.',
            ]);

        // Now set a password and try again
        $user->update(['password_hash' => Hash::make('NewSecret123!')]);

        $successResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/social/google');

        $successResponse->assertStatus(200);
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }
}
