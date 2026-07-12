<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAnalysis extends Model
{
    use HasFactory;

    protected $table = 'ai_analyses';

    protected $fillable = [
        'target_type',
        'target_id',
        'provider',
        'score',
        'result_json',
        'raw_response',
    ];

    protected $casts = [
        'result_json' => 'array',
        'score' => 'float',
    ];
}
