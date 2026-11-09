<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use App\Services\NotificationService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function unreadCount()
    {
        return response()->json(['count' => ChatMessage::whereIn('sender_role', ['customer', 'driver'])->whereNull('read_at')->count()]);
    }

    public function index(Request $request, SupabaseStorageService $storage)
    {
        $search = trim((string) $request->query('search'));
        $role = $request->query('role');
        $unread = $request->boolean('unread');
        $conversations = ChatConversation::with(['participant', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_role', '!=', 'admin')->where('sender_role', '!=', 'staff')->whereNull('read_at')])
            ->when(in_array($role, ['customer', 'driver'], true), fn ($q) => $q->where('participant_role', $role))
            ->when($unread, fn ($q) => $q->whereHas('messages', fn ($m) => $m->whereNotIn('sender_role', ['admin', 'staff'])->whereNull('read_at')))
            ->when($search !== '', fn ($q) => $q->whereHas('participant', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%'])))
            ->orderByDesc('last_message_at')->get();
        $selected = $request->filled('conversation') ? ChatConversation::with(['participant', 'messages.sender'])->findOrFail($request->query('conversation')) : $conversations->first();
        if ($selected) $selected->messages()->where('sender_role', '!=', 'admin')->where('sender_role', '!=', 'staff')->whereNull('read_at')->update(['read_at' => now()]);
        foreach ($conversations as $conversation) {
            $this->setParticipantAvatarUrl($conversation->participant, $storage);
        }
        if ($selected) {
            $this->setParticipantAvatarUrl($selected->participant, $storage);
        }
        return view('admin.chat.index', compact('conversations', 'selected', 'search', 'role', 'unread'));
    }

    private function setParticipantAvatarUrl(?User $user, SupabaseStorageService $storage): void
    {
        if (!$user || !$user->avatar_path) {
            return;
        }

        try {
            $user->setAttribute('avatar_url', $storage->signedProfilePhotoUrl((string) $user->id, $user->avatar_path));
        } catch (\Throwable) {
            $user->setAttribute('avatar_url', null);
        }
    }

    public function reply(Request $request, ChatConversation $conversation, ChatService $chat, NotificationService $notifications)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:4000'], 'order_id' => ['nullable', 'uuid']]);
        $message = $chat->send($conversation, $request->user(), $data['content'], $data['order_id'] ?? null);
        $participant = $conversation->participant;
        $notifications->sendToUser($participant, 'Admin replied', 'You have a new response from AQUA CLAYNE support.', ['entity_type' => 'chat', 'entity_id' => $conversation->id]);
        return redirect()->route('admin.chat.index', ['conversation' => $conversation->id])->with('success', 'Reply sent.');
    }
}
