<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailDeliveryService
{
    /**
     * Send password reset verification code.
     * Supports:
     * 1. Brevo REST API (HTTPS port 443 - free 300/day, unblocked on Railway)
     * 2. Resend REST API (HTTPS port 443 - free 100/day, unblocked on Railway)
     * 3. Native Laravel Mailer (SMTP / testing / log)
     */
    public function sendPasswordResetOtp(string $toEmail, string $otp): void
    {
        $subject = 'AQUA CLAYNE - Password Reset Verification Code';
        $body = "Your AQUA CLAYNE password reset verification code is: {$otp}\n\nThis verification code expires in 5 minutes. Do not share this code with anyone.\n\n— AQUA CLAYNE Purified Drinking Water";

        if (app()->environment('testing')) {
            Mail::raw($body, function ($mail) use ($toEmail, $subject) {
                $mail->to($toEmail)->subject($subject);
            });
            return;
        }

        // 1. Brevo HTTPS API (Port 443 - bypasses Railway SMTP block)
        $brevoKey = env('BREVO_API_KEY');
        if (!empty($brevoKey)) {
            $fromEmail = env('MAIL_FROM_ADDRESS', 'aquaclayne.noreply@gmail.com');
            $fromName = env('MAIL_FROM_NAME', 'AQUA CLAYNE');

            $response = Http::withHeaders([
                'api-key' => trim($brevoKey),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['name' => $fromName, 'email' => $fromEmail],
                    'to' => [['email' => $toEmail]],
                    'subject' => $subject,
                    'textContent' => $body,
                ]);

            if ($response->successful()) {
                Log::info("Password reset OTP sent via Brevo HTTPS API to {$toEmail}");
                return;
            }

            Log::error('Brevo API failed: ' . $response->body());
            throw new \Exception('Brevo API error: ' . ($response->json('message') ?? $response->body()));
        }

        // 2. Resend HTTPS API (Port 443 - bypasses Railway SMTP block)
        $resendKey = env('RESEND_API_KEY') ?? config('services.resend.key');
        if (!empty($resendKey)) {
            $fromEmail = env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev');
            $fromName = env('MAIL_FROM_NAME', 'AQUA CLAYNE');

            $response = Http::withToken(trim($resendKey))
                ->timeout(10)
                ->post('https://api.resend.com/emails', [
                    'from' => "{$fromName} <{$fromEmail}>",
                    'to' => [$toEmail],
                    'subject' => $subject,
                    'text' => $body,
                ]);

            if ($response->successful()) {
                Log::info("Password reset OTP sent via Resend HTTPS API to {$toEmail}");
                return;
            }

            Log::error('Resend API failed: ' . $response->body());
            throw new \Exception('Resend API error: ' . ($response->json('message') ?? $response->body()));
        }

        // 3. Fallback to standard Laravel mailer (e.g. SMTP)
        Mail::raw($body, function ($mail) use ($toEmail, $subject) {
            $mail->to($toEmail)->subject($subject);
        });
    }
}
