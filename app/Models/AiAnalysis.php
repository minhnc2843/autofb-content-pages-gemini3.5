<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAnalysis extends Model
{
    use HasFactory;

    protected $table = 'ai_analyses';

    protected $fillable = [
        'page_id',
        'target_type',
        'target_id',
        'provider',
        'score',
        'result_json',
        'raw_response',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    protected $casts = [
        'result_json' => 'array',
        'score' => 'float',
    ];
}
