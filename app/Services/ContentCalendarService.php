<?php

namespace App\Services;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Topic;
use Carbon\Carbon;

class ContentCalendarService
{
    protected DuplicateProtectionService $dupService;
    protected CaptionService $captionService;

    public function __construct()
    {
        $this->dupService = new DuplicateProtectionService();
        $this->captionService = new CaptionService();
    }

    /**
     * Auto generate draft posts schedule.
     */
    public function generateSchedule(int $days = 7, int $postsPerDay = 3): int
    {
        $topics = Topic::where('is_active', true)->get();
        if ($topics->isEmpty()) {
            $topics = Topic::all();
        }

        if ($topics->isEmpty()) {
            return 0; // No topics to generate content for
        }

        $generatedCount = 0;
        $topicIndex = 0;

        // Determine schedule times based on posts per day
        $times = [];
        if ($postsPerDay === 1) {
            $times = ['13:00'];
        } elseif ($postsPerDay === 2) {
            $times = ['08:00', '20:00'];
        } else {
            $times = ['08:00', '13:00', '20:00']; // default 3 posts/day
        }

        // Start scheduling from tomorrow
        for ($i = 1; $i <= $days; $i++) {
            $date = Carbon::tomorrow()->addDays($i - 1);

            foreach ($times as $time) {
                $scheduledAtStr = $date->format('Y-m-d') . ' ' . $time . ':00';
                
                // 1. Check if slot is already taken
                if ($this->dupService->isSlotTaken($scheduledAtStr)) {
                    continue;
                }

                // 2. Select topic round-robin
                $topic = $topics->get($topicIndex % $topics->count());
                $topicIndex++;

                // 3. Find unique media item matching topic
                $media = MediaItem::where('url', 'like', '%' . $topic->keyword . '%')
                    ->orWhere('thumbnail_url', 'like', '%' . $topic->keyword . '%')
                    ->get()
                    ->first(function ($item) {
                        return !$this->dupService->isDuplicateMedia($item->id);
                    });

                // Fallback to any unused media item
                if (!$media) {
                    $media = MediaItem::get()->first(function ($item) {
                        return !$this->dupService->isDuplicateMedia($item->id);
                    });
                }

                // 4. Generate caption using templates
                $mediaArray = $media ? $media->toArray() : ['type' => 'text', 'photographer' => 'N/A'];
                $caption = $this->captionService->generate($topic->toArray(), $mediaArray, 'english');

                // If duplicate caption, try another generation
                $attempts = 0;
                while ($this->dupService->isDuplicateCaption($caption) && $attempts < 5) {
                    $caption = $this->captionService->generate($topic->toArray(), $mediaArray, 'english');
                    $attempts++;
                }

                // 5. Create Draft Post
                PostQueue::create([
                    'topic_id' => $topic->id,
                    'media_item_id' => $media?->id,
                    'caption' => $caption,
                    'status' => 'draft',
                    'scheduled_at' => $scheduledAtStr,
                ]);

                $generatedCount++;
            }
        }

        return $generatedCount;
    }
}
