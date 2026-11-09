<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // One ongoing support thread per customer or driver. Order context
            // may be attached to individual messages without fragmenting history.
            $table->foreignUuid('participant_id')->unique()->constrained('users');
            $table->string('participant_role', 20);
            $table->uuid('last_message_id')->nullable();
            $table->timestampTz('last_message_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users');
            $table->string('sender_role', 20);
            $table->uuid('recipient_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->text('content');
            $table->timestampTz('read_at')->nullable()->index();
            $table->timestampsTz();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
