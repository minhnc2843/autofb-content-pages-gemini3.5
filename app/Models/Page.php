<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'platform',
        'facebook_page_id',
        'facebook_page_name',
        'facebook_page_link',
        'access_token',
        'token_expires_at',
        'publish_mode',
        'is_active',
        'timezone',
        'language',
        'country',
        'niche',
        'content_tone',
        'notes',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($page) {
            if (empty($page->slug)) {
                $slug = Str::slug($page->name);
                $originalSlug = $slug;
                $count = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$originalSlug}-{$count}";
                    $count++;
                }
                $page->slug = $slug;
            }
        });
    }

    public function profile()
    {
        return $this->hasOne(PageProfile::class);
    }

    public function topics()
    {
        return $this->hasMany(PageTopic::class);
    }

    public function postsQueue()
    {
        return $this->hasMany(PostQueue::class);
    }
}
