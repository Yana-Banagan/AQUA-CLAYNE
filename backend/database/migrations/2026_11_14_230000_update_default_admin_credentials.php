<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $targetEmail = 'aquaclayne.noreply@gmail.com';
        $hashedPassword = Hash::make('aquaclaynenianton');

        // Look for the default admin account (either already under the target email or under previous test/admin emails)
        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            $admin->forceFill([
                'email' => $targetEmail,
                'password_hash' => $hashedPassword,
                'status' => 'active',
            ])->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive
    }
};
