<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }
}
