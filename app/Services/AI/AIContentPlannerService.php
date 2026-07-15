<?php

namespace App\Services\AI;

use App\Models\Page;
use App\Models\PageTopic;
use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Services\PexelsService;
use App\Services\CaptionService;
use App\Services\AI\GeminiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AIContentPlannerService
{
    protected PexelsService $pexelsService;
    protected CaptionService $captionService;
    protected GeminiService $geminiService;

    public function __construct()
    {
        $this->pexelsService = new PexelsService();
        $this->captionService = new CaptionService();
        $this->geminiService = new GeminiService();
    }

    /**
     * Generate content plan for a page.
     */
    public function generatePlanForPage(Page $page, array $options): array
    {
        $days = intval($options['days'] ?? 7);
        $postsPerDay = intval($options['posts_per_day'] ?? $page->profile->max_posts_per_day ?? 3);
        $tone = $options['tone'] ?? $page->content_tone ?? 'calm';
        $language = $options['language'] ?? $page->language ?? 'english';

        // 1. Get posting slots
        $slots = $page->profile->posting_slots ?? ['07:30', '12:30', '20:30'];
        if (count($slots) < $postsPerDay) {
            // Fill slots if posts_per_day exceeds slots list
            $defaultSlots = ['07:30', '12:30', '20:30', '09:00', '15:00', '21:00'];
            $slots = array_slice(array_unique(array_merge($slots, $defaultSlots)), 0, $postsPerDay);
        } else {
            $slots = array_slice($slots, 0, $postsPerDay);
        }

        // 2. Get active topics
        $topics = $page->topics()->where('is_active', true)->get();
        if ($topics->isEmpty()) {
            // Create default fallback topic
            $fallbackTopic = PageTopic::create([
                'page_id' => $page->id,
                'name' => $page->niche ?: 'General',
                'keyword' => $page->niche ?: 'beautiful',
                'is_active' => true,
            ]);
            $topics = collect([$fallbackTopic]);
        }

        // 3. Determine content mix
        $mix = $page->profile->content_mix ?? ['photo' => 50, 'video' => 50, 'text' => 0];
        $photoRatio = intval($mix['photo'] ?? 50);
        $videoRatio = intval($mix['video'] ?? 50);
        $textRatio = intval($mix['text'] ?? 0);
        $totalRatio = $photoRatio + $videoRatio + $textRatio ?: 100;

        $plan = [];
        $topicIndex = 0;

        for ($d = 1; $d <= $days; $d++) {
            foreach ($slots as $slotIndex => $slot) {
                // Select media type based on content mix
                $rand = rand(1, $totalRatio);
                if ($rand <= $photoRatio) {
                    $mediaType = 'photo';
                } elseif ($rand <= $photoRatio + $videoRatio) {
                    $mediaType = 'video';
                } else {
                    $mediaType = 'text';
                }

                // Select topic round-robin
                $topic = $topics->get($topicIndex % $topics->count());
                $topicIndex++;

                $plan[] = [
                    'day' => $d,
                    'slot' => $slot,
                    'media_type' => $mediaType,
                    'topic' => $topic->name,
                    'topic_keyword' => $topic->keyword,
                    'caption_direction' => "Write an engaging caption focusing on '{$topic->name}' with a tone of '{$tone}'. Include 2-3 relevant hashtags.",
                    'reason' => "Aligned with the theme '{$topic->name}' and the tone '{$tone}' for the target slot '{$slot}'.",
                ];
            }
        }

        return $plan;
    }

    /**
     * Create draft posts from a plan.
     */
    public function createDraftsFromPlan(Page $page, array $plan): array
    {
        $createdPosts = [];
        $errors = [];

        foreach ($plan as $item) {
            $mediaItem = null;

            if ($item['media_type'] !== 'text') {
                // Search Pexels for media
                $keyword = $item['topic_keyword'] ?? $item['topic'] ?? $page->niche ?? 'nature';
                try {
                    $searchResult = $this->pexelsService->search($keyword, $item['media_type'], 5);
                    if (!isset($searchResult['error']) && !empty($searchResult['data'])) {
                        // Pick a random media item
                        $mediaData = $searchResult['data'][array_rand($searchResult['data'])];
                        
                        $mediaItem = MediaItem::create([
                            'page_id' => $page->id,
                            'pexels_id' => $mediaData['pexels_id'],
                            'type' => $mediaData['type'],
                            'url' => $mediaData['url'],
                            'thumbnail_url' => $mediaData['thumbnail_url'],
                            'width' => $mediaData['width'] ?? null,
                            'height' => $mediaData['height'] ?? null,
                            'duration' => $mediaData['duration'] ?? null,
                            'photographer' => $mediaData['photographer'] ?? null,
                            'photographer_url' => $mediaData['photographer_url'] ?? null,
                            'pexels_url' => $mediaData['pexels_url'] ?? null,
                            'raw_json' => is_array($mediaData['raw_json']) ? json_encode($mediaData['raw_json']) : $mediaData['raw_json'],
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning("Pexels search failed for planner: " . $e->getMessage());
                }

                // Fallback to cached/mock media item if search failed
                if (!$mediaItem) {
                    $mediaItem = MediaItem::where('type', $item['media_type'])->orderBy('created_at', 'desc')->first();
                }

                // Final fallback: Create a dummy placeholder item
                if (!$mediaItem) {
                    $url = $item['media_type'] === 'video' 
                        ? 'https://player.vimeo.com/external/371433846.sd.mp4?s=236da2f3c0227e339f37c9a59cf6ff38a7c29373&profile_id=139&oauth2_token_id=57447761'
                        : 'https://images.pexels.com/photos/3225517/pexels-photo-3225517.jpeg?auto=compress&cs=tinysrgb&w=1600';

                    $mediaItem = MediaItem::create([
                        'page_id' => $page->id,
                        'pexels_id' => 'placeholder_' . uniqid(),
                        'type' => $item['media_type'],
                        'url' => $url,
                        'thumbnail_url' => $url,
                        'photographer' => 'Pexels Planner',
                    ]);
                }
            }

            // Generate Caption
            $caption = null;
            $language = $page->language ?? 'english';
            $tone = $page->content_tone ?? 'calm';

            if ($this->geminiService->isEnabled()) {
                $prompt = "Write a social media post caption in language: " . strtoupper($language) . ".\n" .
                          "Topic details: Name is '{$item['topic']}', Keyword is '" . ($item['topic_keyword'] ?? '') . "'.\n" .
                          "Niche context: '{$page->niche}'.\n" .
                          "Desired tone: '{$tone}'.\n" .
                          "Guidelines: {$item['caption_direction']}.\n" .
                          "Output ONLY the caption text. No JSON, no markdown formatting like ```, just raw text.";
                
                try {
                    $result = $this->geminiService->generateText($prompt);
                    if (!empty($result['text'])) {
                        $caption = trim($result['text']);
                    }
                } catch (\Exception $e) {
                    Log::warning("Gemini generation failed for planner caption: " . $e->getMessage());
                }
            }

            // Fallback to standard CaptionService template
            if (empty($caption)) {
                $topicData = [
                    'name' => $item['topic'],
                    'keyword' => $item['topic_keyword'] ?? $item['topic'],
                ];
                $mediaData = $mediaItem ? $mediaItem->toArray() : ['photographer' => 'Nature Planner'];
                $caption = $this->captionService->generate($topicData, $mediaData, $language);
            }

            // Calculate Scheduled Date/Time
            $dayOffset = intval($item['day']);
            $scheduledAt = Carbon::tomorrow($page->timezone ?: 'Asia/Ho_Chi_Minh')
                ->addDays($dayOffset - 1)
                ->setTimeFromTimeString($item['slot'])
                ->setTimezone(config('app.timezone', 'UTC'));

            // Resolve page topic ID if possible
            $resolvedTopic = PageTopic::where('page_id', $page->id)
                ->where('name', $item['topic'])
                ->first();

            // Create draft post
            $post = PostQueue::create([
                'page_id' => $page->id,
                'topic_id' => $resolvedTopic ? $resolvedTopic->id : null,
                'media_item_id' => $mediaItem ? $mediaItem->id : null,
                'caption' => $caption,
                'status' => 'draft',
                'scheduled_at' => $scheduledAt,
            ]);

            $createdPosts[] = $post;
        }

        return [
            'success' => true,
            'message' => "Successfully created " . count($createdPosts) . " drafts in queue.",
            'created_count' => count($createdPosts),
            'posts' => collect($createdPosts)->pluck('id')->toArray(),
        ];
    }
}
