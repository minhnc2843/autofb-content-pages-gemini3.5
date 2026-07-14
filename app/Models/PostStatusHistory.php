<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'post_status_histories';

    protected $fillable = [
        'post_queue_id',
        'from_status',
        'to_status',
        'changed_by',
    ];

    public function postQueue()
    {
        return $this->belongsTo(PostQueue::class, 'post_queue_id');
    }
}
