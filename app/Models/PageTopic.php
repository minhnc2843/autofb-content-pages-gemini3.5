<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'name',
        'keyword',
        'language',
        'priority',
        'cooldown_days',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'cooldown_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
