<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageInsight extends Model
{
    use HasFactory;

    protected $table = 'page_insights';

    protected $fillable = [
        'metric',
        'period',
        'values_json',
        'fetched_date',
    ];

    protected $casts = [
        'values_json' => 'array',
        'fetched_date' => 'date',
    ];
}
