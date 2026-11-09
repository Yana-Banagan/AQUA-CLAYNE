<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $supportedProviders = ['google', 'facebook'];

    /**
     * Start the official OAuth redirect flow for Google or Facebook.
     */
    public function redirect(string $provider, Request $request)
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
            $driver = Socialite::driver($provider)->stateless();

            if ($provider === 'google') {
                $driver->scopes(['openid', 'profile', 'email']);
            } elseif ($provider === 'facebook') {
                $driver->scopes(['email', 'public_profile']);
            }

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
     * Handle the OAuth callback from Google or Facebook.
     */
    public function callback(string $provider, Request $request, SupabaseStorageService $storage)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, $this->supportedProviders, true)) {
            return $this->error("Unsupported social provider: {$provider}", 422);
        }

        // Check for error returned by provider (e.g. user canceled consent)
        if ($request->has('error') || $request->has('error_code')) {
            $errorCode = $request->input('error') ?? $request->input('error_code');
            $errorMessage = $request->input('error_description') ?? 'Authentication was canceled or denied by the user.';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'canceled',
                    'message' => $errorMessage,
                    'error_code' => $errorCode,
                ], 400);
            }

            return $this->renderCallbackHtml([
                'success' => false,
                'status' => 'canceled',
                'message' => $errorMessage,
            ]);
        }

        if (!$this->isProviderConfigured($provider)) {
            return $this->error(ucfirst($provider) . ' Sign-In is not configured on the server.', 503);
        }

        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->user();
            return $this->processSocialUser($provider, $socialiteUser, $storage, $request);
        } catch (\Throwable $e) {
            report($e);
            $msg = 'Failed to validate ' . ucfirst($provider) . ' credentials. Please try again.';

            if ($request->wantsJson()) {
                return $this->error($msg, 401);
            }

            return $this->renderCallbackHtml([
                'success' => false,
                'status' => 'error',
                'message' => $msg,
            ]);
        }
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
            report($e);
            return $this->error('Invalid or expired ' . ucfirst($provider) . ' authentication token.', 401);
        }
    }

    /**
     * Process social user: login, link, or register.
     */
    protected function processSocialUser(string $provider, $socialiteUser, SupabaseStorageService $storage, Request $request)
    {
        $providerId = (string) $socialiteUser->getId();
        $email = $socialiteUser->getEmail();
        $name = trim($socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'Aqua Clayne Customer');
        $avatar = $socialiteUser->getAvatar();

        if (empty($providerId)) {
            return $this->error('Failed to obtain provider identifier.', 422);
        }

        // Case 1: Social account already linked
        $socialAccount = SocialAccount::with('user')->where('provider', $provider)
            ->where('provider_user_id', $providerId)
            ->first();

        if ($socialAccount && $socialAccount->user) {
            $user = $socialAccount->user;

            if ($user->status !== 'active') {
                return $this->error('Your account is inactive. Please contact the administrator.', 403);
            }

            if ($avatar && empty($socialAccount->avatar_url)) {
                $socialAccount->update(['avatar_url' => $avatar]);
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
                    return $this->error('Your account is inactive. Please contact the administrator.', 403);
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
            report($e);
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

    protected function renderCallbackHtml(array $payload)
    {
        $json = htmlspecialchars(json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars($payload['data']['token'] ?? '', ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($payload['status'] ?? '', ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($payload['message'] ?? 'Authentication complete.', ENT_QUOTES, 'UTF-8');
        $deepLink = "aquaclayne://auth/callback?status={$status}&token={$token}";

        return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AQUA CLAYNE - Authentication</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #0878C9 0%, #064B87 100%);
            color: #ffffff;
            text-align: center;
            padding: 24px;
            box-sizing: border-box;
        }
        .card {
            background: rgba(255, 255, 255, 0.98);
            color: #12344D;
            padding: 36px 28px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-width: 420px;
            width: 100%;
        }
        .logo {
            font-size: 26px;
            font-weight: 800;
            color: #0878C9;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .message {
            font-size: 15px;
            color: #52718B;
            margin: 16px 0 24px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #0878C9, #064B87);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.92; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">AQUA CLAYNE</div>
        <p class="message">{$message}</p>
        <a class="btn" href="{$deepLink}">Return to AQUA CLAYNE</a>
    </div>
    <script>
        // Attempt automatic deep link navigation
        window.location.href = "{$deepLink}";
        // Send postMessage if inside web popup
        if (window.opener) {
            window.opener.postMessage({$json}, "*");
        }
    </script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']);
    }
}
