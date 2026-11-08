<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasUuids;

    protected $fillable = ['participant_id', 'participant_role', 'last_message_id', 'last_message_at'];
    protected function casts(): array { return ['last_message_at' => 'datetime']; }
    public function participant(): BelongsTo { return $this->belongsTo(User::class, 'participant_id'); }
    public function messages(): HasMany { return $this->hasMany(ChatMessage::class, 'conversation_id'); }
    public function latestMessage(): BelongsTo { return $this->belongsTo(ChatMessage::class, 'last_message_id'); }
}
