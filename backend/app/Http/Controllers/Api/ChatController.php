<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\ChatService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function show(Request $request, ChatService $chat)
    {
        $conversation = $chat->conversationFor($request->user());
        $conversation->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return $this->success($this->formatConversation($conversation->fresh(), true));
    }

    public function send(Request $request, ChatService $chat, NotificationService $notifications)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:4000'], 'order_id' => ['nullable', 'uuid']]);
        $conversation = $chat->conversationFor($request->user());
        $message = $chat->send($conversation, $request->user(), $data['content'], $data['order_id'] ?? null);

        User::whereIn('role', ['admin', 'staff'])->where('status', 'active')->get()->each(function (User $admin) use ($request, $conversation, $message, $notifications) {
            $notifications->sendToUser($admin, 'New chat message', "{$request->user()->name} ({$conversation->participant_role}) sent a message.", ['entity_type' => 'chat', 'entity_id' => $conversation->id]);
        });
        return $this->success($this->formatMessage($message), 'Message sent', 201);
    }

    private function formatConversation(ChatConversation $conversation, bool $includeMessages = false): array
    {
        $result = ['id' => (string) $conversation->id, 'participant_role' => $conversation->participant_role, 'last_message_at' => $conversation->last_message_at?->toISOString()];
        if ($includeMessages) $result['messages'] = $conversation->messages()->with('sender')->oldest('created_at')->get()->map(fn ($m) => $this->formatMessage($m))->values();
        return $result;
    }
    private function formatMessage($message): array
    {
        return ['id' => (string) $message->id, 'conversation_id' => (string) $message->conversation_id, 'sender_id' => (string) $message->sender_id, 'sender_role' => $message->sender_role, 'content' => $message->content, 'order_id' => $message->order_id, 'created_at' => $message->created_at?->toISOString(), 'read_at' => $message->read_at?->toISOString()];
    }
}
