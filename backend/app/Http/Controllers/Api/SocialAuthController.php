<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialAuthAttempt;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $supportedProviders = ['google', 'facebook'];

    /**
     * Start the mobile OAuth flow. The legacy redirect URL remains an alias
     * so older clients are not sent through an uncorrelated OAuth request.
     */
    public function redirect(string $provider, Request $request)
    {
        return $this->mobileRedirect($provider, $request);
    }

    public function mobileRedirect(string $provider, Request $request)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, $this->supportedProviders, true)) {
            return $this->error("Unsupported social provider: {$provider}", 422);
        }

        if (!$this->isProviderConfigured($provider)) {
            $name = ucfirst($provider);
            $clientIdKey = strtoupper($provider) . '_CLIENT_ID';
            $clientSecretKey = strtoupper($provider) . '_CLIENT_SECRET';

            return response()->json([
                'success' => false,
                'status' => 'unconfigured',
                'provider' => $provider,
                'message' => "{$name} Sign-In is not configured yet. Server administrator must set {$clientIdKey} and {$clientSecretKey} in the backend environment.",
            ], 503);
        }

        try {
            $state = 'acm_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            SocialAuthAttempt::create([
                'flow' => 'mobile',
                'provider' => $provider,
                'state_hash' => hash('sha256', $state),
                'expires_at' => now()->addMinutes(10),
            ]);

            $driver = $this->configuredProviderDriver($provider)
                ->stateless()
                ->with(['state' => $state]);

            $targetUrl = $driver->redirect()->getTargetUrl();

            if ($request->wantsJson() || $request->query('format') === 'json') {
                return $this->success([
                    'redirect_url' => $targetUrl,
                    'provider' => $provider,
                ], 'OAuth redirect URL generated.');
            }

            return redirect()->away($targetUrl);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Failed to initiate ' . ucfirst($provider) . ' authentication. Please try again.', 500);
        }
    }

    /**
     * Handle an OAuth callback correlated to a mobile sign-in attempt.
     */
    public function callback(string $provider, Request $request, SupabaseStorageService $storage)
    {
        return $this->mobileCallback($provider, $request, $storage);
    }

    public function mobileCallback(string $provider, Request $request, SupabaseStorageService $storage)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, $this->supportedProviders, true)) {
            return $this->renderMobileCallbackPage('error', null, 'Unsupported sign-in provider. Please return to the app and try again.');
        }

        $state = (string) $request->query('state', '');
        if (!preg_match('/\Aacm_[A-Za-z0-9_-]{43}\z/D', $state)) {
            return $this->renderMobileCallbackPage('error', null, 'This sign-in attempt is invalid or has expired. Please return to the app and try again.');
        }

        $attempt = DB::transaction(function () use ($provider, $state) {
            $attempt = SocialAuthAttempt::where('flow', 'mobile')
                ->where('provider', $provider)
                ->where('state_hash', hash('sha256', $state))
                ->lockForUpdate()
                ->first();

            if (!$attempt || $attempt->expires_at->isPast() || $attempt->state_used_at !== null) {
                return null;
            }

            $attempt->state_used_at = now();
            $attempt->save();

            return $attempt;
        });

        if (!$attempt) {
            return $this->renderMobileCallbackPage('error', null, 'This sign-in attempt is invalid, expired, or already used. Please return to the app and try again.');
        }

        if ($request->has('error') || $request->has('error_code')) {
            return $this->renderMobileCallbackPage('canceled', null, ucfirst($provider) . ' sign-in was canceled. You can return to the app and try again.');
        }

        if (!$this->isProviderConfigured($provider)) {
            return $this->renderMobileCallbackPage('error', null, ucfirst($provider) . ' sign-in is not configured on the server. Please try again later.');
        }

        try {
            $socialiteUser = $this->configuredProviderDriver($provider)->stateless()->user();
            return $this->processSocialUser($provider, $socialiteUser, $storage, $request, $attempt);
        } catch (\Throwable $e) {
            Log::warning('Mobile social sign-in failed', [
                'provider' => $provider,
                'exception' => get_class($e),
            ]);
            return $this->renderMobileCallbackPage('error', null, 'Could not verify your ' . ucfirst($provider) . ' sign-in. Please return to the app and try again.');
        }
    }

    /**
     * Exchange a short-lived, single-use mobile authorization code for a
     * Sanctum token. Only the code hash is persisted.
     */
    public function exchange(Request $request, SupabaseStorageService $storage)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43}\z/D'],
        ]);
        $codeHash = hash('sha256', $validated['code']);

        $result = DB::transaction(function () use ($codeHash) {
            $attempt = SocialAuthAttempt::where('flow', 'mobile')
                ->whereIn('provider', $this->supportedProviders)
                ->where('code_hash', $codeHash)
                ->lockForUpdate()
                ->first();

            if (
                !$attempt ||
                !$attempt->state_used_at ||
                $attempt->expires_at->isPast() ||
                $attempt->used_at !== null
            ) {
                return ['status' => 'invalid'];
            }

            $attempt->used_at = now();
            $attempt->save();

            $user = User::with('customer')->find($attempt->user_id);
            if (!$user || $user->status !== 'active' || $user->role !== 'customer' || !$user->customer) {
                return ['status' => 'forbidden'];
            }

            return [
                'status' => 'ok',
                'user' => $user,
                'token' => $user->createToken('api')->plainTextToken,
            ];
        });

        if ($result['status'] === 'forbidden') {
            return $this->error('This customer account is no longer available for sign-in.', 403);
        }
        if ($result['status'] !== 'ok') {
            return $this->error('The sign-in code is invalid, expired, or already used. Please try again.', 422);
        }

        return $this->success([
            'token' => $result['token'],
            'user' => $this->userData($result['user'], $storage),
        ], 'Authentication successful.');
    }

    /**
     * Verify token directly from mobile SDK / client app.
     */
    public function verifyToken(string $provider, Request $request, SupabaseStorageService $storage)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, $this->supportedProviders, true)) {
            return $this->error("Unsupported social provider: {$provider}", 422);
        }

        $token = $request->input('access_token') ?? $request->input('token') ?? $request->input('id_token');

        // Allow mock/testing tokens in test/local environments
        if (app()->environment('local', 'testing') && $request->has('mock_user')) {
            $mockData = $request->input('mock_user');
            $mockUser = new \Laravel\Socialite\Two\User();
            $mockUser->map([
                'id' => $mockData['id'] ?? ('mock_' . Str::random(10)),
                'name' => $mockData['name'] ?? 'Test Social User',
                'email' => $mockData['email'] ?? null,
                'avatar' => $mockData['avatar'] ?? null,
            ]);
            return $this->processSocialUser($provider, $mockUser, $storage, $request);
        }

        if (empty($token)) {
            return $this->error('Authentication token is required.', 422);
        }

        if (!$this->isProviderConfigured($provider)) {
            return response()->json([
                'success' => false,
                'status' => 'unconfigured',
                'provider' => $provider,
                'message' => ucfirst($provider) . ' Sign-In is not configured yet. Server administrator must set credentials in the backend environment.',
            ], 503);
        }

        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->userFromToken($token);
            return $this->processSocialUser($provider, $socialiteUser, $storage, $request);
        } catch (\Throwable $e) {
            Log::warning('Social provider token verification failed', [
                'provider' => $provider,
                'exception' => get_class($e),
            ]);
            return $this->error('Invalid or expired ' . ucfirst($provider) . ' authentication token.', 401);
        }
    }

    /**
     * Process social user: login, link, or register.
     */
    protected function processSocialUser(
        string $provider,
        $socialiteUser,
        SupabaseStorageService $storage,
        Request $request,
        ?SocialAuthAttempt $mobileAttempt = null
    )
    {
        $providerId = (string) $socialiteUser->getId();
        $email = $socialiteUser->getEmail();
        $name = trim($socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'Aqua Clayne Customer');
        $avatar = $socialiteUser->getAvatar();

        if (empty($providerId)) {
            if ($mobileAttempt) {
                return $this->renderMobileCallbackPage('error', null, 'Could not verify your provider account. Please return to the app and try again.');
            }
            return $this->error('Failed to obtain provider identifier.', 422);
        }

        // Case 1: Social account already linked
        $socialAccount = SocialAccount::with('user')->where('provider', $provider)
            ->where('provider_user_id', $providerId)
            ->first();

        if ($socialAccount && $socialAccount->user) {
            $user = $socialAccount->user;

            if ($user->status !== 'active') {
                if ($mobileAttempt) {
                    return $this->renderMobileCallbackPage('error', null, 'Your account is inactive. Please contact the administrator.');
                }
                return $this->error('Your account is inactive. Please contact the administrator.', 403);
            }
            if ($mobileAttempt && ($user->role !== 'customer' || !$user->customer)) {
                return $this->renderMobileCallbackPage('error', null, 'This account is not permitted to sign in to the customer app.');
            }

            if ($avatar && empty($socialAccount->avatar_url)) {
                $socialAccount->update(['avatar_url' => $avatar]);
            }

            if ($mobileAttempt) {
                return $this->completeMobileSignIn($user, $mobileAttempt);
            }

            $token = $user->createToken('api')->plainTextToken;
            $data = [
                'user' => $this->userData($user, $storage),
                'token' => $token,
                'is_new_user' => false,
            ];

            if ($request->wantsJson()) {
                return $this->success($data, 'Signed in successfully with ' . ucfirst($provider) . '.');
            }

            return $this->renderCallbackHtml([
                'success' => true,
                'status' => 'authenticated',
                'data' => $data,
            ]);
        }

        // Case 2: Email matches an existing account -> require password verification to link securely
        if (!empty($email)) {
            $normalizedEmail = strtolower(trim($email));
            $existingUser = User::where('email', $normalizedEmail)->first();

            if ($existingUser) {
                if ($existingUser->status !== 'active') {
                    if ($mobileAttempt) {
                        return $this->renderMobileCallbackPage('error', null, 'Your account is inactive. Please contact the administrator.');
                    }
                    return $this->error('Your account is inactive. Please contact the administrator.', 403);
                }

                if ($mobileAttempt) {
                    return $this->renderMobileCallbackPage(
                        'needs_linking',
                        null,
                        'An Aqua Clayne account already uses this email. Sign in with that account first, then link your social account from your profile.'
                    );
                }

                // Security requirement: DO NOT automatically merge accounts solely because the email matches.
                // Generate a temporary 15-minute link token.
                $linkToken = Str::random(64);
                Cache::put("social_link:{$linkToken}", [
                    'user_id' => $existingUser->id,
                    'email' => $existingUser->email,
                    'provider' => $provider,
                    'provider_user_id' => $providerId,
                    'provider_email' => $normalizedEmail,
                    'avatar_url' => $avatar,
                    'name' => $name,
                ], now()->addMinutes(15));

                $linkResponseData = [
                    'needs_linking' => true,
                    'provider' => $provider,
                    'email' => $existingUser->email,
                    'link_token' => $linkToken,
                ];

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'status' => 'needs_linking',
                        'message' => 'An account with email ' . $existingUser->email . ' already exists. Please verify your account password to link your ' . ucfirst($provider) . ' account.',
                        'data' => $linkResponseData,
                    ], 409);
                }

                return $this->renderCallbackHtml([
                    'success' => false,
                    'status' => 'needs_linking',
                    'message' => 'Account already exists. Please complete password verification in the app to link your ' . ucfirst($provider) . ' account.',
                    'data' => $linkResponseData,
                ]);
            }
        }

        // Case 3: New customer registration via social account
        $isPlaceholderEmail = false;
        if (empty($email)) {
            // Handle situations where Facebook does not provide an email address
            $email = "fb_{$providerId}@facebook.aquaclayne.local";
            $isPlaceholderEmail = true;
        } else {
            $email = strtolower(trim($email));
        }

        // Generate clean unique username
        $baseUsername = Str::slug(Str::before($email, '@'), '');
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'user_' . substr(md5($providerId), 0, 6);
        }
        $baseUsername = substr($baseUsername, 0, 20);
        $username = $baseUsername;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = substr($baseUsername, 0, 16) . $counter;
            $counter++;
        }

        $newUser = DB::transaction(function () use ($name, $username, $email, $avatar, $provider, $providerId) {
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => null,
                'password_hash' => Hash::make(Str::random(64)),
                'role' => 'customer', // Strictly customer role, never privileged
                'status' => 'active',
            ]);

            $user->customer()->create([
                'birthdate' => null,
            ]);

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'provider_email' => $email,
                'avatar_url' => $avatar,
            ]);

            return $user;
        });

        if ($mobileAttempt) {
            return $this->completeMobileSignIn($newUser, $mobileAttempt);
        }

        $token = $newUser->createToken('api')->plainTextToken;
        $data = [
            'user' => $this->userData($newUser, $storage),
            'token' => $token,
            'is_new_user' => true,
            'needs_profile_completion' => $isPlaceholderEmail || empty($newUser->phone),
        ];

        if ($request->wantsJson()) {
            return $this->success($data, 'Account registered and signed in with ' . ucfirst($provider) . '.', 201);
        }

        return $this->renderCallbackHtml([
            'success' => true,
            'status' => 'registered',
            'data' => $data,
        ]);
    }

    /**
     * Complete verified account linking with password confirmation.
     */
    public function linkAccount(Request $request, SupabaseStorageService $storage)
    {
        $validated = $request->validate([
            'link_token' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $linkData = Cache::get("social_link:{$validated['link_token']}");
        if (!$linkData) {
            return $this->error('The account linking session has expired or is invalid. Please start sign in again.', 422);
        }

        $user = User::find($linkData['user_id']);
        if (!$user) {
            Cache::forget("social_link:{$validated['link_token']}");
            return $this->error('Associated user account was not found.', 404);
        }

        if ($user->status !== 'active') {
            return $this->error('Your account is inactive. Please contact the administrator.', 403);
        }

        // Verify existing account password
        if (empty($user->password_hash) || !Hash::check($validated['password'], $user->password_hash)) {
            return $this->error('The password you entered is incorrect. Account linking cancelled.', 422);
        }

        // Link social identity to existing user record
        $user->socialAccounts()->updateOrCreate(
            [
                'provider' => $linkData['provider'],
                'provider_user_id' => $linkData['provider_user_id'],
            ],
            [
                'provider_email' => $linkData['provider_email'] ?? $user->email,
                'avatar_url' => $linkData['avatar_url'] ?? null,
            ]
        );

        Cache::forget("social_link:{$validated['link_token']}");

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => $this->userData($user, $storage),
            'token' => $token,
            'linked' => true,
        ], 'Your account has been successfully linked with ' . ucfirst($linkData['provider']) . '.');
    }

    /**
     * Link social identity for an already authenticated user.
     */
    public function linkCurrentAccount(string $provider, Request $request, SupabaseStorageService $storage)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, $this->supportedProviders, true)) {
            return $this->error("Unsupported social provider: {$provider}", 422);
        }

        $token = $request->input('access_token') ?? $request->input('token') ?? $request->input('id_token');
        if (empty($token)) {
            return $this->error('Provider authentication token is required.', 422);
        }

        if (!$this->isProviderConfigured($provider)) {
            return $this->error(ucfirst($provider) . ' Sign-In is not configured on the server.', 503);
        }

        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->userFromToken($token);
            $providerId = (string) $socialiteUser->getId();
            $email = $socialiteUser->getEmail();
            $avatar = $socialiteUser->getAvatar();

            // Check if this provider ID is already linked to another account
            $existing = SocialAccount::where('provider', $provider)
                ->where('provider_user_id', $providerId)
                ->first();

            if ($existing && $existing->user_id !== $request->user()->id) {
                return $this->error("This {$provider} account is already connected to another Aqua Clayne user.", 409);
            }

            $user = $request->user();
            $user->socialAccounts()->updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_user_id' => $providerId,
                ],
                [
                    'provider_email' => $email,
                    'avatar_url' => $avatar,
                ]
            );

            return $this->success([
                'user' => $this->userData($user->fresh(), $storage),
            ], ucfirst($provider) . ' account successfully linked.');
        } catch (\Throwable $e) {
            Log::warning('Social account linking failed', [
                'provider' => $provider,
                'exception' => get_class($e),
            ]);
            return $this->error('Failed to link ' . ucfirst($provider) . ' account.', 401);
        }
    }

    /**
     * Unlink social identity for an authenticated user.
     */
    public function unlinkAccount(string $provider, Request $request)
    {
        $provider = strtolower(trim($provider));
        $user = $request->user()->fresh();

        // Safety check: Ensure user has a password or another social provider before unlinking
        $linkedCount = $user->socialAccounts()->count();
        $hasPassword = !empty($user->password_hash);

        if (!$hasPassword && $linkedCount <= 1) {
            return $this->error('You cannot disconnect your only login method. Please set an account password first.', 422);
        }

        $deleted = $user->socialAccounts()->where('provider', $provider)->delete();
        if ($deleted === 0) {
            return $this->error("No linked {$provider} account was found.", 404);
        }

        return $this->success(null, ucfirst($provider) . ' account unlinked successfully.');
    }

    /**
     * List linked social accounts for the authenticated user.
     */
    public function linkedAccounts(Request $request)
    {
        $user = $request->user();
        $linked = $user->socialAccounts()->pluck('provider_email', 'provider')->all();

        $result = [];
        foreach ($this->supportedProviders as $provider) {
            $result[$provider] = [
                'linked' => array_key_exists($provider, $linked),
                'email' => $linked[$provider] ?? null,
            ];
        }

        return $this->success(['providers' => $result]);
    }

    protected function isProviderConfigured(string $provider): bool
    {
        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");

        return !empty($clientId) && !empty($clientSecret);
    }

    protected function configuredProviderDriver(string $provider)
    {
        $driver = Socialite::driver($provider);
        if ($provider === 'google') {
            $driver->scopes(['openid', 'profile', 'email']);
        } elseif ($provider === 'facebook') {
            $driver->scopes(['email', 'public_profile']);
        }

        return $driver;
    }

    protected function completeMobileSignIn(User $user, SocialAuthAttempt $attempt)
    {
        if ($user->status !== 'active' || $user->role !== 'customer' || !$user->customer) {
            return $this->renderMobileCallbackPage('error', null, 'This account is not permitted to sign in to the customer app.');
        }

        $code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $attempt->forceFill([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds(60),
        ])->save();

        return $this->renderMobileCallbackPage('authenticated', $code, 'Sign-in complete. Returning to the Aqua Clayne app.');
    }

    protected function renderMobileCallbackPage(string $status, ?string $code, string $message)
    {
        $query = ['status' => $status];
        if ($code !== null) {
            $query['code'] = $code;
        }
        $deepLink = 'aquaclayne://auth/callback?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $escapedDeepLink = htmlspecialchars($deepLink, ENT_QUOTES, 'UTF-8');
        $javascriptDeepLink = json_encode(
            $deepLink,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aqua Clayne sign-in</title>
</head>
<body>
    <main>
        <h1>Aqua Clayne</h1>
        <p>{$escapedMessage}</p>
        <p>If the app does not open automatically, reopen it and try signing in again.</p>
        <a href="{$escapedDeepLink}">Return to the Aqua Clayne app</a>
    </main>
    <script>window.location.replace({$javascriptDeepLink});</script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    protected function renderCallbackHtml(array $payload)
    {
        return $this->renderMobileCallbackPage(
            (string) ($payload['status'] ?? 'error'),
            null,
            (string) ($payload['message'] ?? 'Sign-in could not be completed. Please return to the app and try again.')
        );
    }

    protected function userData(User $user, SupabaseStorageService $storage): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'avatar_url' => $user->avatar_path
                ? $storage->signedProfilePhotoUrl((string) $user->id, $user->avatar_path)
                : ($user->socialAccounts()->first()?->avatar_url ?? null),
        ];
    }

}
