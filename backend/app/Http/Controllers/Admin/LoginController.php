<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\SocialAuthController;
use App\Models\SocialAccount;
use App\Models\SocialAuthAttempt;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    protected array $supportedProviders = ['google', 'facebook'];

    /**
     * Display the unified login form for all roles.
     */
    public function showLoginForm(Request $request = null)
    {
        if ($request && ($request->has('switch') || $request->has('logout') || $request->has('force'))) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            return view('admin.login');
        }

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'customer') {
                return redirect()->route('customer.index');
            }
            if (in_array($user->role, ['admin', 'staff'])) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('customer.index');
        }

        return view('admin.login');
    }

    /**
     * Authenticate user credentials and redirect dynamically by role.
     */
    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $identifier = trim($request->input('login') ?? $request->input('email') ?? $request->input('identifier') ?? '');

        if (empty($identifier)) {
            return back()->withErrors([
                'login' => 'Please enter your email address or username.'
            ])->withInput();
        }

        // Look up by email (case-insensitive) or exact username
        $user = User::where('email', strtolower($identifier))
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            return back()->withErrors([
                'login' => 'Invalid credentials.'
            ])->withInput();
        }

        if ($user->status !== 'active') {
            return back()->withErrors([
                'login' => 'Your account is inactive. Please contact support.'
            ])->withInput();
        }

        if (!Hash::check($request->input('password'), $user->password_hash)) {
            return back()->withErrors([
                'login' => 'Invalid credentials.'
            ])->withInput();
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);

        $request->session()->regenerate();

        // Role-based redirection from authenticated database role
        if ($user->role === 'customer') {
            return redirect()->intended(route('customer.index'));
        }

        if (in_array($user->role, ['admin', 'staff'])) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('customer.index'));
    }

    /**
     * Start the OAuth redirect flow for Google or Facebook (Web).
     */
    public function redirectToProvider(string $provider, Request $request)
    {
        $provider = strtolower(trim($provider));

        if (!in_array($provider, $this->supportedProviders, true)) {
            return redirect()->route('admin.login')->with('error', "Unsupported social provider: {$provider}");
        }

        if (!$this->isProviderConfigured($provider)) {
            $name = ucfirst($provider);
            return redirect()->route('admin.login')->with(
                'error',
                "{$name} Sign-In is not configured in this environment. Please sign in using your Aqua Clayne credentials."
            );
        }

        try {
            $state = 'acw_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            SocialAuthAttempt::create([
                'flow' => 'web',
                'provider' => $provider,
                'state_hash' => hash('sha256', $state),
                'expires_at' => now()->addMinutes(10),
            ]);

            // Prefer the configured redirect URI from services config (.env GOOGLE_REDIRECT_URI)
            $callbackUrl = config("services.{$provider}.redirect");
            if (empty($callbackUrl)) {
                $callbackUrl = route('auth.social.callback', ['provider' => $provider]);
            }

            // Dynamically match current request host (127.0.0.1 vs localhost)
            $currentHost = $request->getSchemeAndHttpHost();
            $parsedScheme = parse_url($callbackUrl, PHP_URL_SCHEME);
            $parsedHost = parse_url($callbackUrl, PHP_URL_HOST);
            $parsedPort = parse_url($callbackUrl, PHP_URL_PORT);
            if ($parsedScheme && $parsedHost) {
                $configuredOrigin = $parsedScheme . '://' . $parsedHost . ($parsedPort ? ':' . $parsedPort : '');
                if (
                    (str_contains($configuredOrigin, '127.0.0.1') && str_contains($currentHost, 'localhost')) ||
                    (str_contains($configuredOrigin, 'localhost') && str_contains($currentHost, '127.0.0.1'))
                ) {
                    $callbackUrl = str_replace($configuredOrigin, $currentHost, $callbackUrl);
                }
            }

            $driver = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl($callbackUrl)
                ->with(['state' => $state]);

            if ($provider === 'google') {
                $driver->scopes(['openid', 'profile', 'email']);
            } elseif ($provider === 'facebook') {
                $driver->scopes(['email', 'public_profile']);
            }

            return $driver->redirect();
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.login')->with(
                'error',
                'Failed to connect to ' . ucfirst($provider) . '. Please try again.'
            );
        }
    }

    /**
     * Handle the OAuth callback from Google or Facebook (Web).
     */
    public function handleProviderCallback(
        string $provider,
        Request $request,
        SocialAuthController $mobileSocialAuth,
        SupabaseStorageService $storage
    ) {
        $provider = strtolower(trim($provider));

        if (!in_array($provider, $this->supportedProviders, true)) {
            return redirect()->route('admin.login')->with('error', "Unsupported social provider: {$provider}");
        }

        $state = (string) $request->query('state', '');
        if (str_starts_with($state, 'acm_')) {
            return $mobileSocialAuth->mobileCallback($provider, $request, $storage);
        }

        if (!preg_match('/\Aacw_[A-Za-z0-9_-]{43}\z/D', $state) || !$this->consumeWebState($provider, $state)) {
            return redirect()->route('admin.login')->with(
                'error',
                'This sign-in attempt is invalid, expired, or already used. Please try again.'
            );
        }

        // Check if user canceled or provider returned error
        if ($request->has('error') || $request->has('error_code')) {
            $errorDesc = $request->input('error_description') ?? 'Authentication canceled.';
            return redirect()->route('admin.login')->with('error', "Sign-in with " . ucfirst($provider) . " was canceled: {$errorDesc}");
        }

        if (!$this->isProviderConfigured($provider)) {
            return redirect()->route('admin.login')->with(
                'error',
                ucfirst($provider) . ' Sign-In is not configured on this server.'
            );
        }

        try {
            $callbackUrl = $request->url();
            $socialiteUser = null;

            try {
                $socialiteUser = Socialite::driver($provider)->stateless()->redirectUrl($callbackUrl)->user();
            } catch (\Throwable $ex) {
                $fallbackUrl = config("services.{$provider}.redirect");
                if ($fallbackUrl && $fallbackUrl !== $callbackUrl) {
                    $socialiteUser = Socialite::driver($provider)->stateless()->redirectUrl($fallbackUrl)->user();
                } else {
                    throw $ex;
                }
            }

            $providerId = (string) $socialiteUser->getId();
            $email = $socialiteUser->getEmail();
            $name = trim($socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'Aqua Clayne Customer');
            $avatar = $socialiteUser->getAvatar();

            if (empty($providerId)) {
                return redirect()->route('admin.login')->with('error', 'Unable to retrieve account ID from ' . ucfirst($provider));
            }

            // 1. Existing linked social account
            $socialAccount = SocialAccount::with('user')
                ->where('provider', $provider)
                ->where('provider_user_id', $providerId)
                ->first();

            if ($socialAccount && $socialAccount->user) {
                $user = $socialAccount->user;

                if ($user->status !== 'active') {
                    return redirect()->route('admin.login')->withErrors([
                        'login' => 'Your account is inactive. Please contact support.'
                    ]);
                }

                if ($avatar && empty($socialAccount->avatar_url)) {
                    $socialAccount->update(['avatar_url' => $avatar]);
                }

                Auth::login($user, true);
                $request->session()->regenerate();

                if ($user->role === 'customer') {
                    return redirect()->intended(route('customer.index'));
                }
                if (in_array($user->role, ['admin', 'staff'])) {
                    return redirect()->intended(route('admin.dashboard'));
                }
                return redirect()->intended(route('customer.index'));
            }

            // 2. Matching email found in users table
            if (!empty($email)) {
                $normalizedEmail = strtolower(trim($email));
                $existingUser = User::where('email', $normalizedEmail)->first();

                if ($existingUser) {
                    if ($existingUser->status !== 'active') {
                        return redirect()->route('admin.login')->withErrors([
                            'login' => 'Your account is inactive. Please contact support.'
                        ]);
                    }

                    // Security: Never grant admin or staff access via social linking
                    if (in_array($existingUser->role, ['admin', 'staff'])) {
                        return redirect()->route('admin.login')->with(
                            'error',
                            'Administrative and Staff accounts must log in with email/username and password for security.'
                        );
                    }

                    // Link customer account and log in
                    $existingUser->socialAccounts()->firstOrCreate([
                        'provider' => $provider,
                        'provider_user_id' => $providerId,
                    ], [
                        'provider_email' => $normalizedEmail,
                        'avatar_url' => $avatar,
                    ]);

                    Auth::login($existingUser, true);
                    $request->session()->regenerate();
                    return redirect()->intended(route('customer.index'));
                }
            }

            // 3. New Customer Registration via Social Provider (strictly customer role)
            if (empty($email)) {
                $email = "fb_{$providerId}@facebook.aquaclayne.local";
            } else {
                $email = strtolower(trim($email));
            }

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
                    'role' => 'customer', // strictly customer role
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

            Auth::login($newUser, true);
            $request->session()->regenerate();

            return redirect()->intended(route('customer.index'));

        } catch (\Throwable $e) {
            Log::warning('Web social sign-in failed', [
                'provider' => $provider,
                'exception' => get_class($e),
            ]);
            return redirect()->route('admin.login')->with(
                'error',
                'Authentication with ' . ucfirst($provider) . ' failed. Please try again.'
            );
        }
    }

    protected function consumeWebState(string $provider, string $state): bool
    {
        return DB::transaction(function () use ($provider, $state) {
            $attempt = SocialAuthAttempt::where('flow', 'web')
                ->where('provider', $provider)
                ->where('state_hash', hash('sha256', $state))
                ->lockForUpdate()
                ->first();

            if (!$attempt || $attempt->expires_at->isPast() || $attempt->state_used_at !== null) {
                return false;
            }

            $attempt->state_used_at = now();
            $attempt->save();

            return true;
        });
    }

    /**
     * Check if client ID and secret are set for the provider.
     */
    protected function isProviderConfigured(string $provider): bool
    {
        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");

        return !empty($clientId) && !empty($clientSecret);
    }

    /**
     * Log out current session and redirect to landing or login.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
