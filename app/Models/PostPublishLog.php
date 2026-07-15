<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostPublishLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'post_queue_id',
        'mode',
        'provider',
        'action',
        'status',
        'request_summary',
        'response_json',
        'error_message',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    protected $casts = [
        'request_summary' => 'array',
        'response_json' => 'array',
    ];

    public function postQueue()
    {
        return $this->belongsTo(PostQueue::class, 'post_queue_id');
    }
}
