<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'description',
        'audience',
        'content_goals',
        'avoid_topics',
        'preferred_media_types',
        'content_mix',
        'posting_slots',
        'approval_mode',
        'auto_approve_min_score',
        'max_posts_per_day',
        'hashtag_policy',
        'language_policy',
    ];

    protected $casts = [
        'preferred_media_types' => 'array',
        'content_mix' => 'array',
        'posting_slots' => 'array',
        'auto_approve_min_score' => 'integer',
        'max_posts_per_day' => 'integer',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
