<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
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
