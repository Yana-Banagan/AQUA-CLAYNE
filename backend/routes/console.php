<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventory;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:update-credentials {current-email} {new-email}', function (): int {
    $currentEmail = (string) $this->argument('current-email');
    $newEmail = (string) $this->argument('new-email');

    $emailValidation = Validator::make(
        ['email' => $newEmail],
        ['email' => ['required', 'email', 'max:150']]
    );
    if ($emailValidation->fails()) {
        $this->error('The new administrator email is invalid.');
        return 1;
    }

    $admin = User::query()
        ->where('email', $currentEmail)
        ->where('role', 'admin')
        ->first();

    if (!$admin) {
        $this->error('No administrator account matches the supplied current email.');
        return 1;
    }

    if (User::query()->where('email', $newEmail)->where('id', '!=', $admin->getKey())->exists()) {
        $this->error('The new email is already assigned to another account. No changes were made.');
        return 1;
    }

    $password = $this->secret('New administrator password (input hidden)');
    $confirmation = $this->secret('Confirm new administrator password (input hidden)');
    if (!is_string($password) || strlen($password) < 12 || !hash_equals($password, (string) $confirmation)) {
        $this->error('The password must be at least 12 characters and both entries must match. No changes were made.');
        return 1;
    }

    try {
        DB::transaction(function () use ($admin, $newEmail, $password): void {
            $admin->forceFill([
                'email' => $newEmail,
                'password_hash' => Hash::make($password),
            ])->save();

            $verifiedAdmin = User::query()->findOrFail($admin->getKey());
            if ($verifiedAdmin->role !== 'admin'
                || $verifiedAdmin->email !== $newEmail
                || !Hash::check($password, $verifiedAdmin->password_hash)
                || !Auth::guard('web')->validate(['email' => $newEmail, 'password' => $password])) {
                throw new \RuntimeException('Credential verification failed.');
            }
        });
    } catch (\RuntimeException $exception) {
        $this->error($exception->getMessage() === 'Credential verification failed.'
            ? $exception->getMessage() . ' No changes were committed.'
            : 'Could not update administrator credentials. No changes were committed.');
        return 1;
    }

    $this->info('Administrator email and password updated and verified. The administrator role is unchanged.');
    return 0;
})->purpose('Safely update one existing administrator account; prompts for the password without echoing it');

// Refill services use a daily station allocation. Reset every inventory record
// to the standard 100-unit stock at the start of each day.
Schedule::call(function (): void {
    Inventory::query()->update(['quantity_on_hand' => 100]);
})->dailyAt('00:00')->name('renew-refill-service-stock')->withoutOverlapping();
