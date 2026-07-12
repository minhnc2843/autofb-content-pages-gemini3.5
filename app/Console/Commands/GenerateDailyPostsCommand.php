<?php

namespace App\Console\Commands;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Services\CaptionService;
use App\Services\PexelsService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateDailyPostsCommand extends Command
{
    protected $signature = 'posts:generate-daily';
    protected $description = 'Generate daily draft posts for active topics';

    protected array $defaultSlots = ['08:00', '13:00', '20:00'];

    public function handle(): int
    {
        $this->info('Starting daily post generation...');

        $topics = Topic::where('is_active', true)->get();

        if ($topics->isEmpty()) {
            $this->warn('No active topics found.');
            return self::SUCCESS;
        }

        $today = Carbon::today();
        $captionService = new CaptionService();
        $totalCreated = 0;

        foreach ($topics as $topic) {
            $this->info("Processing topic: {$topic->name}");

            // Check how many posts already exist for today
            $existingCount = PostQueue::where('topic_id', $topic->id)
                ->whereDate('scheduled_at', $today)
                ->count();

            if ($existingCount >= 3) {
                $this->info("  Topic '{$topic->name}' already has {$existingCount} posts for today. Skipping.");
                continue;
            }

            $slotsNeeded = 3 - $existingCount;
            $availableSlots = array_slice($this->defaultSlots, $existingCount, $slotsNeeded);

            // Try to search Pexels for media
            $mediaResults = [];
            try {
                $pexelsService = new PexelsService();
                $result = $pexelsService->search($topic->keyword, $topic->media_type, $slotsNeeded);

                if (!isset($result['error'])) {
                    $mediaResults = $result['data'] ?? [];
                } else {
                    $this->warn("  Pexels search error: {$result['error']}");
                }
            } catch (\RuntimeException $e) {
                $this->warn("  Pexels API not configured: {$e->getMessage()}");
                $this->info("  Creating posts without media...");
            }

            foreach ($availableSlots as $index => $slot) {
                $scheduledAt = $today->copy()->setTimeFromTimeString($slot);

                // Use media if available
                $mediaItem = null;
                $mediaData = $mediaResults[$index] ?? null;

                if ($mediaData) {
                    $mediaItem = \App\Models\MediaItem::updateOrCreate(
                        ['pexels_id' => $mediaData['pexels_id']],
                        [
                            'type' => $mediaData['type'],
                            'url' => $mediaData['url'],
                            'thumbnail_url' => $mediaData['thumbnail_url'],
                            'width' => $mediaData['width'] ?? null,
                            'height' => $mediaData['height'] ?? null,
                            'duration' => $mediaData['duration'] ?? null,
                            'photographer' => $mediaData['photographer'] ?? null,
                            'photographer_url' => $mediaData['photographer_url'] ?? null,
                            'pexels_url' => $mediaData['pexels_url'] ?? null,
                            'raw_json' => $mediaData['raw_json'] ?? null,
                        ]
                    );
                }

                // Generate caption
                $caption = $captionService->generate(
                    $topic->toArray(),
                    $mediaData ?? ['type' => 'photo', 'photographer' => 'Unknown'],
                    $topic->language
                );

                PostQueue::create([
                    'topic_id' => $topic->id,
                    'media_item_id' => $mediaItem?->id,
                    'caption' => $caption,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'draft',
                ]);

                $totalCreated++;
                $this->info("  Created draft for {$slot}");
            }
        }

        $this->info("Daily post generation complete. Created {$totalCreated} draft posts.");
        return self::SUCCESS;
    }
}
