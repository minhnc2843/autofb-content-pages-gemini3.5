<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\Setting;
use App\Models\PostQueue;
use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_disabled_never_makes_http_requests(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'false');
        Setting::setValue('GEMINI_API_KEY', 'some-key');

        $topic = Topic::create(['name' => 'Pets', 'keyword' => 'cat']);
        $media = MediaItem::create([
            'pexels_id' => '1',
            'type' => 'photo',
            'url' => 'https://pexels.com/1.jpg',
            'thumbnail_url' => 'https://pexels.com/1-thumb.jpg'
        ]);

        Http::fake();

        // Try creating captions
        $response = $this->postJson('/ai/generate-captions', [
            'topic_id' => $topic->id,
            'media_item_id' => $media->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['error', 'variants']);
        $this->assertEquals('Gemini AI is disabled. Please enable it in Settings.', $response->json('error'));

        // Assert no external calls were made to Google Gemini API
        Http::assertNothingSent();
    }

    public function test_get_strategy_never_calls_gemini_api(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'some-key');
        Topic::create(['name' => 'Pets', 'keyword' => 'cat']);

        Http::fake();

        // Loading the strategy page via GET
        $response = $this->get('/strategy');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Strategy/Index')
            ->where('geminiEnabled', true)
        );

        // Assert GET /strategy does not call Gemini API automatically
        Http::assertNothingSent();
    }

    public function test_post_strategy_generate_calls_gemini_when_enabled(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'some-key');
        Topic::create(['name' => 'Pets', 'keyword' => 'cat']);

        $jsonOutput = json_encode([
            'strategy_title' => 'Weekly Cat strategy',
            'overview' => 'Cats theme',
            'daily_plan' => [
                ['day' => 'Day 1', 'category' => 'funny', 'focus' => 'Cat playing', 'prompt_suggestion' => 'A funny cat video.']
            ],
            'category_distribution' => ['funny' => 1]
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

        // Post request to trigger strategy generation
        $response = $this->post('/strategy/generate');

        $response->assertRedirect('/strategy');
        $response->assertSessionHas('success');

        // Confirm Gemini API was hit
        Http::assertSentCount(1);
    }
}
