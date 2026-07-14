<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostQueue extends Model
{
    use HasFactory;

    protected $table = 'posts_queue';

    protected static function booted()
    {
        static::updating(function ($post) {
            if ($post->isDirty('status')) {
                $from = $post->getOriginal('status') ?? 'unknown';
                $to = $post->status;
                if ($from !== $to) {
                    $post->statusHistories()->create([
                        'from_status' => $from,
                        'to_status' => $to,
                        'changed_by' => auth()->user()?->name ?? 'system',
                    ]);
                }
            }
        });
    }

    protected $fillable = [
        'topic_id',
        'media_item_id',
        'caption',
        'scheduled_at',
        'publish_started_at',
        'published_at',
        'publish_attempts',
        'status',
        'facebook_post_id',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'publish_started_at' => 'datetime',
        'published_at' => 'datetime',
        'publish_attempts' => 'integer',
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

    public function aiAnalyses()
    {
        return $this->hasMany(AiAnalysis::class, 'target_id')
            ->where('target_type', 'post_queue');
    }

    public function statusHistories()
    {
        return $this->hasMany(PostStatusHistory::class, 'post_queue_id');
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
