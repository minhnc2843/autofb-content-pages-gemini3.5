<?php

namespace App\Services;

use App\Models\PostQueue;
use Carbon\Carbon;

class DuplicateProtectionService
{
    /**
     * Check if a media item has been scheduled/published in the last X days.
     */
    public function isDuplicateMedia(int $mediaItemId, int $withinDays = 30): bool
    {
        $cutoff = Carbon::now()->subDays($withinDays);
        return PostQueue::where('media_item_id', $mediaItemId)
            ->where(function ($q) use ($cutoff) {
                $q->where('scheduled_at', '>=', $cutoff)
                  ->orWhere('created_at', '>=', $cutoff);
            })
            ->exists();
    }

    /**
     * Check if a caption is already exactly matching any post.
     */
    public function isDuplicateCaption(string $caption): bool
    {
        return PostQueue::where('caption', trim($caption))->exists();
    }

    /**
     * Check if a specific slot is already taken.
     */
    public function isSlotTaken(string $dateTimeStr): bool
    {
        $dt = Carbon::parse($dateTimeStr)->format('Y-m-d H:i');
        return PostQueue::whereNotNull('scheduled_at')
            ->get()
            ->contains(function ($post) use ($dt) {
                return $post->scheduled_at->format('Y-m-d H:i') === $dt;
            });
    }
}
