<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'title',
        'status',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function messages()
    {
        return $this->hasMany(AiChatMessage::class, 'session_id');
    }
}
