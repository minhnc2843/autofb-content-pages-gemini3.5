<?php

namespace App\Services;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Topic;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
     * Retains backward compatibility with the original signature.
     */
    public function generateSchedule(int $days = 7, int $postsPerDay = 3): int
    {
        $summary = $this->generateScheduleForDays($days, [
            'posts_per_day' => $postsPerDay,
        ]);

        return $summary['created'];
    }

    /**
     * Complete Auto schedule generator with parameters, validation, and Pexels integration.
     */
    public function generateScheduleForDays(int $days = 7, array $options = []): array
    {
        $postsPerDay = intval($options['posts_per_day'] ?? 3);
        $startDateStr = $options['start_date'] ?? null;
        $topicIds = $options['topic_ids'] ?? null;
        $mediaType = $options['media_type'] ?? 'both';
        $status = $options['status'] ?? 'draft';

        $summary = [
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'days' => $days,
            'posts_per_day' => $postsPerDay,
        ];

        // 1. Validate parameters
        if (!in_array($days, [1, 7, 14, 30])) {
            $summary['errors'][] = "Invalid days parameter. Allowed values: 1, 7, 14, 30.";
            return $summary;
        }

        if ($postsPerDay < 1 || $postsPerDay > 10) {
            $summary['errors'][] = "posts_per_day must be between 1 and 10.";
            return $summary;
        }

        // 2. Fetch active topics
        $topicsQuery = Topic::where('is_active', true);
        if (!empty($topicIds)) {
            $topicsQuery->whereIn('id', $topicIds);
        }
        $topics = $topicsQuery->get();

        if ($topics->isEmpty()) {
            $summary['errors'][] = "No active topics found matching the criteria.";
            return $summary;
        }

        // 3. Determine timeslots
        $defaultSlots = ["08:00", "13:00", "20:00"];
        if ($postsPerDay > count($defaultSlots)) {
            $slots = [];
            for ($s = 0; $s < $postsPerDay; $s++) {
                $hour = 8 + intval($s * (12 / max(1, $postsPerDay - 1)));
                $slots[] = sprintf('%02d:00', $hour);
            }
        } else {
            $slots = array_slice($defaultSlots, 0, $postsPerDay);
        }

        $startDate = $startDateStr ? Carbon::parse($startDateStr) : Carbon::tomorrow();
        $topicIndex = 0;

        $geminiEnabled = Setting::getValue('GEMINI_ENABLED') === 'true';
        $captionMode = Setting::getValue('GEMINI_CAPTION_MODE', 'template');

        // Loop through each day
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Skip if day already has enough scheduled posts
            if ($this->dupService->dayHasEnoughPosts($date, $postsPerDay)) {
                $summary['skipped'] += count($slots);
                continue;
            }

            foreach ($slots as $time) {
                $scheduledAtStr = $date->toDateString() . ' ' . $time . ':00';

                // Check slot taken
                if ($this->dupService->isSlotTaken($scheduledAtStr)) {
                    $summary['skipped']++;
                    continue;
                }

                // Get topic round-robin
                $topic = $topics->get($topicIndex % $topics->count());
                $topicIndex++;

                // Search media cache
                $mediaQuery = MediaItem::query();
                if ($mediaType !== 'both') {
                    $mediaQuery->where('type', $mediaType);
                }
                $keyword = $topic->keyword;
                $mediaQuery->where(function ($q) use ($keyword) {
                    $q->where('url', 'like', "%{$keyword}%")
                      ->orWhere('thumbnail_url', 'like', "%{$keyword}%");
                });

                $media = $mediaQuery->get()->first(function ($item) {
                    return !$this->dupService->isMediaRecentlyUsed($item->id, 30);
                });

                // Fallback to any unused cached media item matching type
                if (!$media) {
                    $fallbackQuery = MediaItem::query();
                    if ($mediaType !== 'both') {
                        $fallbackQuery->where('type', $mediaType);
                    }
                    $media = $fallbackQuery->get()->first(function ($item) {
                        return !$this->dupService->isMediaRecentlyUsed($item->id, 30);
                    });
                }

                // If still no media and Pexels key exists, call Pexels API
                $pexelsApiKey = Setting::getValue('PEXELS_API_KEY');
                if (!$media && !empty($pexelsApiKey)) {
                    try {
                        $pexels = new PexelsService();
                        $result = $pexels->search($topic->keyword, $mediaType, 5);
                        if (!isset($result['error']) && !empty($result['data'])) {
                            // Find first unused item
                            foreach ($result['data'] as $itemData) {
                                // Check if this pexels_id exists in cache
                                $existingItem = MediaItem::where('pexels_id', $itemData['pexels_id'])->first();
                                if ($existingItem) {
                                    if (!$this->dupService->isMediaRecentlyUsed($existingItem->id, 30)) {
                                        $media = $existingItem;
                                        break;
                                    }
                                } else {
                                    // Create new media item
                                    $media = MediaItem::create([
                                        'pexels_id' => $itemData['pexels_id'],
                                        'type' => $itemData['type'],
                                        'url' => $itemData['url'],
                                        'thumbnail_url' => $itemData['thumbnail_url'],
                                        'width' => $itemData['width'] ?? null,
                                        'height' => $itemData['height'] ?? null,
                                        'duration' => $itemData['duration'] ?? null,
                                        'photographer' => $itemData['photographer'] ?? null,
                                        'photographer_url' => $itemData['photographer_url'] ?? null,
                                        'pexels_url' => $itemData['pexels_url'] ?? null,
                                        'raw_json' => is_array($itemData['raw_json']) ? json_encode($itemData['raw_json']) : $itemData['raw_json'],
                                    ]);
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Pexels search fail in scheduler: " . $e->getMessage());
                    }
                }

                // If still no media, check if we need to report error
                if (!$media) {
                    $summary['errors'][] = "No unused media found for topic: {$topic->name}";
                    $summary['failed']++;
                    continue;
                }

                // Generate Caption
                $mediaArray = $media->toArray();
                $caption = null;

                if ($geminiEnabled && $captionMode === 'ai') {
                    try {
                        $gemini = new \App\Services\AI\GeminiService();
                        if ($gemini->isEnabled()) {
                            $aiResult = $gemini->generateCaptionVariants($topic->toArray(), $mediaArray, 'english', 'facebook_engagement');
                            if (!empty($aiResult['variants'])) {
                                $caption = $aiResult['variants'][0];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Gemini caption generate fail: " . $e->getMessage());
                    }
                }

                if (empty($caption)) {
                    $caption = $this->captionService->generate($topic->toArray(), $mediaArray, 'english');
                }

                // Avoid exact duplicate caption
                $attempts = 0;
                while ($this->dupService->isCaptionDuplicate($caption) && $attempts < 5) {
                    if ($geminiEnabled && $captionMode === 'ai') {
                        try {
                            $gemini = new \App\Services\AI\GeminiService();
                            if ($gemini->isEnabled()) {
                                $aiResult = $gemini->generateCaptionVariants($topic->toArray(), $mediaArray, 'english', 'facebook_engagement');
                                if (!empty($aiResult['variants'])) {
                                    $caption = $aiResult['variants'][($attempts + 1) % count($aiResult['variants'])];
                                } else {
                                    $caption = $this->captionService->generate($topic->toArray(), $mediaArray, 'english');
                                }
                            }
                        } catch (\Exception $e) {
                            $caption = $this->captionService->generate($topic->toArray(), $mediaArray, 'english');
                        }
                    } else {
                        $caption = $this->captionService->generate($topic->toArray(), $mediaArray, 'english');
                    }
                    $attempts++;
                }

                // Create Post Queue
                PostQueue::create([
                    'topic_id' => $topic->id,
                    'media_item_id' => $media->id,
                    'caption' => $caption,
                    'status' => $status,
                    'scheduled_at' => $scheduledAtStr,
                ]);

                $summary['created']++;
            }
        }

        return $summary;
    }
}
