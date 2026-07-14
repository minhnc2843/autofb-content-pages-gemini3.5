<?php

namespace App\Services;

use App\Models\PostQueue;
use Carbon\Carbon;

class DuplicateProtectionService
{
    /**
     * Normalize a caption for comparison (lowercase, collapse spacing).
     */
    public function normalizeCaption(string $caption): string
    {
        $normalized = mb_strtolower(trim($caption));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }

    /**
     * Check if a media item has been scheduled/published in the last X days.
     */
    public function isMediaRecentlyUsed(int $mediaItemId, int $days = 30): bool
    {
        $cutoff = Carbon::now()->subDays($days);
        return PostQueue::where('media_item_id', $mediaItemId)
            ->where(function ($q) use ($cutoff) {
                $q->where('scheduled_at', '>=', $cutoff)
                  ->orWhere('created_at', '>=', $cutoff);
            })
            ->exists();
    }

    /**
     * Alias for isMediaRecentlyUsed to keep compatibility.
     */
    public function isDuplicateMedia(int $mediaItemId, int $withinDays = 30): bool
    {
        return $this->isMediaRecentlyUsed($mediaItemId, $withinDays);
    }

    /**
     * Check if a caption is a duplicate after normalization.
     */
    public function isCaptionDuplicate(string $caption): bool
    {
        $normalized = $this->normalizeCaption($caption);
        return PostQueue::select('caption')->get()->contains(function ($post) use ($normalized) {
            return $this->normalizeCaption($post->caption) === $normalized;
        });
    }

    /**
     * Alias for isCaptionDuplicate to keep compatibility.
     */
    public function isDuplicateCaption(string $caption): bool
    {
        return $this->isCaptionDuplicate($caption);
    }

    /**
     * Check if a specific slot is already taken using direct DB query.
     */
    public function isSlotTaken(string|Carbon $dateTime): bool
    {
        $dt = Carbon::parse($dateTime);
        $start = $dt->copy()->startOfMinute();
        $end = $dt->copy()->endOfMinute();
        return PostQueue::whereBetween('scheduled_at', [$start, $end])->exists();
    }

    /**
     * Check if a specific date already has enough scheduled posts.
     */
    public function dayHasEnoughPosts(string|Carbon $date, int $limit): bool
    {
        $dt = Carbon::parse($date);
        $count = PostQueue::whereDate('scheduled_at', $dt->toDateString())->count();
        return $count >= $limit;
    }
}
