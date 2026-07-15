<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'chat_session_id',
        'type',
        'status',
        'user_prompt',
        'plan_json',
        'result_json',
        'error_message',
        'requires_confirmation',
    ];

    protected $casts = [
        'plan_json' => 'array',
        'result_json' => 'array',
        'requires_confirmation' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function chatSession()
    {
        return $this->belongsTo(AiChatSession::class, 'chat_session_id');
    }
}
