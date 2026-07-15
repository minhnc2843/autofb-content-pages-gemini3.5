<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingSecret extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_session_id',
        'ai_chat_session_id',
        'secret_type',
        'encrypted_value',
        'redacted_label',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'encrypted_value' => 'encrypted',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function chatSession()
    {
        return $this->belongsTo(AiChatSession::class, 'ai_chat_session_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return !is_null($this->consumed_at);
    }
}
