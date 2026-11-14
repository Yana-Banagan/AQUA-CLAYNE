<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_renders_successfully(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('AQUA CLAYNE');
        $response->assertSee('Purified Drinking Water');
        $response->assertSee('Clean Water, Healthier Lives.');
        $response->assertSee('Unified Portal');
        $response->assertSee('Sign in');
        $response->assertSee('admin-login.css');
        $response->assertSee('admin-login.js');
        $response->assertSee(route('admin.login.submit'), false);
        $response->assertSee('forgotModalBackdrop');
        $response->assertSee('otpBoxesContainer');
        $response->assertSee('otpDigit1');
        $response->assertSee('otpDigit6');
        $response->assertSee('otpCountdown');
        $response->assertSee('btnSendOtp');
    }

    public function test_forgot_password_otp_flow_from_email_request_to_successful_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'client@aquaclayne.com',
            'username' => 'aquaclient',
            'password_hash' => Hash::make('OldPassword123!'),
        ]);

        // 1. Send OTP using username or email
        $otpResponse = $this->postJson('/auth/password/otp', [
            'email' => 'aquaclient',
            'digits' => 6,
        ]);

        $otpResponse->assertStatus(200);
        $otpResponse->assertJsonPath('data.email', 'client@aquaclayne.com');
        $otpResponse->assertJsonPath('data.digits', 6);

        // Verify OTP record created in database with 6 digits
        $record = DB::table('password_reset_otps')->where('email', 'client@aquaclayne.com')->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->otp_hash);

        // Test invalid OTP fails
        $invalidVerify = $this->postJson('/auth/password/verify-otp', [
            'email' => 'client@aquaclayne.com',
            'otp' => '000000',
        ]);
        $invalidVerify->assertStatus(422);

        // Set known OTP for testing verification
        $testOtp = '852963';
        DB::table('password_reset_otps')->where('email', 'client@aquaclayne.com')->update([
            'otp_hash' => Hash::make($testOtp),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        // 2. Verify OTP
        $verifyResponse = $this->postJson('/auth/password/verify-otp', [
            'email' => 'client@aquaclayne.com',
            'otp' => $testOtp,
        ]);

        $verifyResponse->assertStatus(200);
        $resetToken = $verifyResponse->json('data.reset_token');
        $this->assertNotEmpty($resetToken);

        // 3. Reset Password
        $newPassword = 'NewSecretPassword2026!';
        $resetResponse = $this->postJson('/auth/password/reset', [
            'email' => 'client@aquaclayne.com',
            'reset_token' => $resetToken,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $resetResponse->assertStatus(200);

        // Verify password updated and OTP record cleared
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password_hash));
        $this->assertDatabaseMissing('password_reset_otps', ['email' => 'client@aquaclayne.com']);
    }
}
