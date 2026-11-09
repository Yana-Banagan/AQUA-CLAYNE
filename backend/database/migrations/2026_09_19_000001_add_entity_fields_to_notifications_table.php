<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('entity_type', 50)->nullable()->after('body');
            $table->uuid('entity_id')->nullable()->after('entity_type');
            $table->unique(
                ['user_id', 'entity_type', 'entity_id'],
                'notifications_user_entity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_entity_unique');
            $table->dropColumn(['entity_type', 'entity_id']);
        });
    }
};
