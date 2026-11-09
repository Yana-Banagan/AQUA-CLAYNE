<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function conversationFor(User $participant): ChatConversation
    {
        return ChatConversation::firstOrCreate(
            ['participant_id' => $participant->id],
            ['participant_role' => $participant->role]
        );
    }

    public function send(ChatConversation $conversation, User $sender, string $content, ?string $orderId = null): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $sender, $content, $orderId) {
            $recipient = $sender->id === $conversation->participant_id
                ? User::whereIn('role', ['admin', 'staff'])->orderBy('created_at')->value('id')
                : $conversation->participant_id;
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id, 'sender_id' => $sender->id,
                'sender_role' => $sender->role, 'recipient_id' => $recipient,
                'order_id' => $orderId, 'content' => trim($content),
            ]);
            $conversation->update(['last_message_id' => $message->id, 'last_message_at' => $message->created_at]);
            try { app(RealtimeEventService::class)->emit('chat', $conversation->id, 'message'); } catch (\Throwable $e) { report($e); }
            return $message;
        });
    }
}
