<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function sendPasswordOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();
        if (!$user) return $this->error('No registered account was found for this email.', 422);

        // The log and array transports do not deliver messages to an inbox.
        // Never claim an OTP was sent unless a real mail transport and sender
        // address are configured.
        $mailer = config('mail.default');
        $fromAddress = config('mail.from.address');
        if (in_array($mailer, ['log', 'array'], true) || empty($fromAddress) || str_ends_with($fromAddress, '@example.com')) {
            return $this->error('Password-reset email delivery is not configured. Please configure SMTP mail settings.', 503);
        }

        // A resend must always issue a fresh code. This also replaces records
        // made before a mail provider was configured, rather than trapping a
        // customer behind an OTP that was never delivered.
        $otp = (string) random_int(1000, 9999);
        DB::table('password_reset_otps')->updateOrInsert(['email' => $email], [
            'otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinute(),
            'attempts' => 0, 'verified_at' => null, 'reset_token_hash' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            Mail::raw("Your AQUA CLAYNE password reset code is {$otp}. It expires in 1 minute. Do not share this code.", function ($mail) use ($email) {
                $mail->to($email)->subject('AQUA CLAYNE Password Reset Code');
            });
        } catch (\Throwable $exception) {
            // Avoid leaving a throttled OTP that can never be received when
            // the provider rejects the message.
            DB::table('password_reset_otps')->where('email', $email)->delete();
            report($exception);
            return $this->error('We could not send the OTP email. Please try again later.', 503);
        }
        return $this->success(null, 'OTP sent to your registered email.');
    }

    public function verifyPasswordOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'otp' => ['required', 'digits:4']]);
        $record = DB::table('password_reset_otps')->where('email', strtolower($data['email']))->first();
        if (!$record || now()->greaterThan($record->expires_at)) return $this->error('This OTP has expired. Request a new code.', 422);
        if ($record->attempts >= 5) return $this->error('Too many incorrect attempts. Request a new OTP.', 429);
        if (!Hash::check($data['otp'], $record->otp_hash)) {
            DB::table('password_reset_otps')->where('email', strtolower($data['email']))->increment('attempts');
            return $this->error('Incorrect OTP. Please try again.', 422);
        }
        $token = Str::random(64);
        DB::table('password_reset_otps')->where('email', strtolower($data['email']))->update([
            'verified_at' => now(), 'reset_token_hash' => Hash::make($token), 'updated_at' => now(),
        ]);
        return $this->success(['reset_token' => $token], 'OTP verified.');
    }

    public function resetPassword(Request $request)
    {
        $commonPasswords = ['password', 'password1', 'password123', '123456', '12345678', 'qwerty', 'admin', 'admin123', 'letmein', 'welcome', 'abc123', 'iloveyou', 'monkey', 'football', 'dragon', 'login'];
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', function ($attribute, $value, $fail) use ($commonPasswords) {
                if (preg_match('/\s/', $value) || !preg_match('/[A-Z]/', $value) || !preg_match('/[a-z]/', $value) || !preg_match('/\d/', $value) || !preg_match('/[^A-Za-z0-9\s]/', $value) || in_array(strtolower($value), $commonPasswords, true)) {
                    $fail('Password does not meet the security requirements.');
                }
            }],
        ]);
        $email = strtolower($data['email']);
        $record = DB::table('password_reset_otps')->where('email', $email)->first();
        if (!$record || !$record->verified_at || now()->greaterThan($record->expires_at) || !Hash::check($data['reset_token'], $record->reset_token_hash ?? '')) return $this->error('Password reset session has expired. Request a new OTP.', 422);
        User::where('email', $email)->update(['password_hash' => Hash::make($data['password'])]);
        DB::table('password_reset_otps')->where('email', $email)->delete();
        return $this->success(null, 'Password updated successfully.');
    }
    public function register(Request $request, SupabaseStorageService $storage)
    {
        $input = $request->all();

        // Normalize values before validating. The server repeats the mobile
        // validation so a modified client cannot create an invalid account.
        foreach (['first_name', 'last_name'] as $key) {
            if (isset($input[$key])) {
                $input[$key] = preg_replace('/\s+/', ' ', trim((string) $input[$key]));
            }
        }
        if (isset($input['email'])) {
            $input['email'] = strtolower(trim((string) $input['email']));
        }
        if (isset($input['username'])) {
            $input['username'] = strtolower(trim((string) $input['username']));
        }
        if (isset($input['phone'])) {
            $input['phone'] = preg_replace('/[\s-]+/', '', (string) $input['phone']);
        }

        if (!empty($input['first_name']) || !empty($input['firstname'])) {
            $first = $input['first_name'] ?? $input['firstname'] ?? '';
            $last = $input['last_name'] ?? $input['lastname'] ?? '';
            $input['name'] = trim("$first $last");
        }

        if (empty($input['phone']) && !empty($input['mobile_number'])) {
            $input['phone'] = $input['mobile_number'];
        }
        if (isset($input['phone'])) {
            $input['phone'] = preg_replace('/[\s-]+/', '', (string) $input['phone']);
        }

        $request->merge($input);

        $allowedDomains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'aol.com', 'protonmail.com', 'zoho.com', 'yandex.com', 'lu.edu.ph'];
        $commonPasswords = ['password', 'password1', 'password123', '123456', '12345678', 'qwerty', 'admin', 'admin123', 'letmein', 'welcome', 'abc123', 'iloveyou', 'monkey', 'football', 'dragon', 'login'];
        $nameRule = ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL]+(?: [\pL]+)*$/u'];

        $validated = $request->validate([
            'first_name' => $nameRule,
            'last_name' => $nameRule,
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', function ($attribute, $value, $fail) use ($allowedDomains) {
                [$local, $domain] = array_pad(explode('@', strtolower($value), 2), 2, '');
                if (str_ends_with($domain, '.co')) {
                    $fail('.co email domains are not accepted.');
                } elseif (strlen($local) < 3 || !in_array($domain, $allowedDomains, true)) {
                    $fail('Please use a supported email address.');
                }
            }],
            'phone' => ['required', 'string', 'unique:users,phone', function ($attribute, $value, $fail) {
                if (strlen($value) > 11) {
                    $fail('Mobile number must not exceed 11 digits.');
                    return;
                }
                if (!preg_match('/^09\d{9}$/', $value)) {
                    $fail('Use exactly 11 digits in the format 09XXXXXXXXX.');
                    return;
                }
                $digits = str_replace('+', '', $value);
                if (preg_match('/(\d)\1\1\1/', $digits) || count(array_unique(str_split($digits))) === 1) {
                    $fail('Please enter a valid mobile number.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed', function ($attribute, $value, $fail) use ($commonPasswords) {
                if (preg_match('/\s/', $value) || !preg_match('/[A-Z]/', $value) || !preg_match('/[a-z]/', $value) || !preg_match('/\d/', $value) || !preg_match('/[^A-Za-z0-9\s]/', $value) || in_array(strtolower($value), $commonPasswords, true)) {
                    $fail('Password does not meet the security requirements.');
                }
            }],
            'birthdate' => ['required', 'date', 'before_or_equal:today', function ($attribute, $value, $fail) {
                $birthdate = Carbon::parse($value);
                if ($birthdate->lt(Carbon::create(1956, 1, 1)) || $birthdate->gt(Carbon::create(2008, 12, 31))) {
                    $fail('Choose a birthdate from 1956 through 2008.');
                    return;
                }
                $age = $birthdate->age;
                if ($age < 18 || $age > 99) {
                    $fail('You must be between 18 and 99 years old to create an account.');
                }
            }],
        ]);

        if (count(preg_split('/\s+/', trim($validated['first_name'] . ' ' . $validated['last_name']))) > 6) {
            return $this->error('Your full name cannot exceed 6 words.', 422);
        }

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password_hash' => Hash::make($validated['password']),

                // NEVER accept role from the request - strictly assign customer
                'role' => 'customer',

                'status' => 'active',
            ]);
            $user->customer()->create(['birthdate' => $validated['birthdate']]);
            return $user;
        });

        return $this->success([
            'user' => $this->userData($user, $storage),
        ], 'Registration successful.', 201);
    }

    public function login(Request $request, SupabaseStorageService $storage)
    {
        $login = trim((string) ($request->input('email') ?? $request->input('username')));

        if (empty($login)) {
            return $this->error('Username or email is required.', 422);
        }

        if (empty($request->input('password'))) {
            return $this->error('Password is required.', 422);
        }

        // Login accepts the uniquely validated username, email, or mobile
        // number. Full-name lookup is intentionally excluded because names
        // are not unique account identifiers.
        $user = User::where('email', strtolower($login))
            ->orWhere('username', strtolower($login))
            ->orWhere('phone', $login)
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password_hash)) {
            return $this->error(
                'Invalid credentials.',
                401
            );
        }

        if ($user->status !== 'active') {
            return $this->error(
                'Your account is inactive. Please contact the administrator.',
                403
            );
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => $this->userData($user, $storage),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(
            null,
            'Logged out.'
        );
    }

    public function me(Request $request, SupabaseStorageService $storage)
    {
        return $this->success([
            'user' => $this->userData($request->user(), $storage),
        ]);
    }

    public function updateFcmToken(Request $request)
    {
        $data = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $request->user()->update([
            'fcm_token' => $data['fcm_token'],
        ]);

        return $this->success(null, 'Token registered');
    }

    public function updateProfile(Request $request, SupabaseStorageService $storage)
    {
        $user = $request->user();

        // Apply the same normalization used during registration before the
        // server validates profile edits. This prevents formatting characters
        // or accidental capitalization from bypassing field rules.
        $input = $request->only(['name', 'username', 'phone', 'email']);
        if (isset($input['name'])) {
            $input['name'] = preg_replace('/\s+/', ' ', trim((string) $input['name']));
        }
        if (isset($input['username'])) {
            $input['username'] = strtolower(trim((string) $input['username']));
        }
        if (isset($input['phone'])) {
            $input['phone'] = preg_replace('/[\s-]+/', '', (string) $input['phone']);
        }
        if (isset($input['email'])) {
            $input['email'] = strtolower(trim((string) $input['email']));
        }
        $request->merge($input);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150', 'regex:/^[\pL]+(?: [\pL]+)*$/u'],
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._]+$/', 'unique:users,username,' . $user->id],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phone,' . $user->id],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return $this->success([
            'user' => $this->userData($user, $storage),
        ], 'Profile updated successfully.');
    }

    public function uploadProfilePhoto(Request $request, SupabaseStorageService $storage)
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $photo = $data['photo'];
        $user = $request->user();
        $ext = strtolower($photo->extension() ?: 'jpg');
        $path = "profiles/{$user->id}/avatar.{$ext}";

        try {
            $storage->upload(
                'profile-photos',
                $path,
                file_get_contents($photo->getRealPath()),
                $photo->getMimeType(),
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Supabase profile photo upload failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'path' => $path,
            ]);

            return $this->error(
                "We couldn't upload your profile picture. Please check your internet connection and try again.",
                502
            );
        }

        $user->update(['avatar_path' => $path]);
        \Illuminate\Support\Facades\Cache::forget('profile-photo-url:' . $user->id . ':' . sha1($path));

        return $this->success([
            'user' => $this->userData($user->fresh(), $storage),
        ], 'Profile photo updated successfully.');
    }

    private function userData(User $user, SupabaseStorageService $storage): array
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
                : null,
        ];
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', function ($attribute, $value, $fail) {
                if (preg_match('/\s/', $value) || !preg_match('/[A-Z]/', $value) || !preg_match('/[a-z]/', $value) || !preg_match('/\d/', $value) || !preg_match('/[^A-Za-z0-9\s]/', $value)) {
                    $fail('Password must include uppercase, lowercase, number, special character, and no spaces.');
                }
            }],
        ]);

        if (!Hash::check($validated['current_password'], $user->password_hash)) {
            return $this->error('The provided current password does not match our records.', 422);
        }

        $user->update([
            'password_hash' => Hash::make($validated['password']),
        ]);

        return $this->success(null, 'Password updated successfully.');
    }
}
