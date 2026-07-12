<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostQueue extends Model
{
    use HasFactory;

    protected $table = 'posts_queue';

    protected $fillable = [
        'topic_id',
        'media_item_id',
        'caption',
        'scheduled_at',
        'status',
        'facebook_post_id',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function mediaItem()
    {
        return $this->belongsTo(MediaItem::class);
    }

    public function publishLogs()
    {
        return $this->hasMany(PostPublishLog::class, 'post_queue_id');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePublishedFake($query)
    {
        return $query->where('status', 'published_fake');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'approved')
            ->where('scheduled_at', '<=', now());
    }
}
