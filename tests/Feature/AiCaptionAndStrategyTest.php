<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\MediaItem;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCaptionAndStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_caption_variants_success(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'valid-key');

        $topic = Topic::create(['name' => 'Pets', 'keyword' => 'cat']);
        $media = MediaItem::create([
            'pexels_id' => '500',
            'type' => 'photo',
            'url' => 'https://pexels.com/500.jpg',
            'thumbnail_url' => 'https://pexels.com/500-thumb.jpg',
            'photographer' => 'Pet Photographer'
        ]);

        $jsonOutput = json_encode([
            'variants' => [
                'Variant A: Look at this cat!',
                'Variant B: Cats are awesome!',
                'Variant C: Cute cat picture.'
            ]
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $jsonOutput]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/ai/generate-captions', [
            'topic_id' => $topic->id,
            'media_item_id' => $media->id,
            'language' => 'english',
            'preset' => 'facebook_engagement'
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'variants');
        $this->assertEquals('Variant A: Look at this cat!', $response->json('variants.0'));
    }

    public function test_strategy_engine_loads(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'valid-key');
        Topic::create(['name' => 'Pets', 'keyword' => 'cat', 'is_active' => true]);

        $jsonOutput = json_encode([
            'strategy_title' => 'Cat Page Strategy',
            'overview' => 'Plan for cat page.',
            'daily_plan' => [
                ['day' => 'Day 1', 'category' => 'educational', 'focus' => 'Cat behavior', 'prompt_suggestion' => 'Post about meowing.']
            ],
            'category_distribution' => ['educational' => 1]
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $jsonOutput]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // Generate first to store in DB
        $this->post('/strategy/generate');

        // Now load strategy index page
        $response = $this->get('/strategy');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Strategy/Index')
            ->has('strategy')
            ->where('strategy.strategy_title', 'Cat Page Strategy')
            ->has('topics', 1)
        );
    }
}
