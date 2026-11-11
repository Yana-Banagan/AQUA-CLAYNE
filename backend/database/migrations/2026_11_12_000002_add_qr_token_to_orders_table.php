<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('assignment_source', 32)->nullable();
            $table->timestamp('assigned_at')->nullable();
        });

        // Give historical orders a durable QR reference as well.
        \Illuminate\Support\Facades\DB::table('orders')->whereNull('qr_token')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update(['qr_token' => hash('sha256', Str::random(80))]);
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['assignment_source', 'assigned_at']);
        });
    }
};
