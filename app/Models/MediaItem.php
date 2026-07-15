<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'pexels_id',
        'type',
        'url',
        'thumbnail_url',
        'width',
        'height',
        'duration',
        'photographer',
        'photographer_url',
        'pexels_url',
        'raw_json',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    protected $casts = [
        'raw_json' => 'array',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
    ];

    public function posts()
    {
        return $this->hasMany(PostQueue::class, 'media_item_id');
    }
}
