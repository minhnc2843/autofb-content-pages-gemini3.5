<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\PostQueue;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_analyze_without_gemini_api_key(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', '');

        // No key set in settings or env
        $post = PostQueue::create([
            'caption' => 'Test post without AI key',
            'status' => 'draft',
        ]);

        $response = $this->post("/queue/{$post->id}/analyze");

        $response->assertRedirect('/queue');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Gemini API key is not configured', session('error'));
    }

    public function test_post_scoring_saves_to_ai_analyses_table(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'valid-key-xyz');

        $jsonOutput = json_encode([
            'score' => 92,
            'strengths' => ['Excellent storytelling'],
            'weaknesses' => ['Too long'],
            'suggestions' => ['Shorten by 10%.']
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

        $post = PostQueue::create([
            'caption' => 'Beautiful sunset scene today.',
            'status' => 'draft',
        ]);

        $response = $this->post("/queue/{$post->id}/analyze");

        $response->assertRedirect('/queue');
        $response->assertSessionHas('success');
        $this->assertStringContainsString('scored by AI successfully', session('success'));

        $this->assertDatabaseHas('ai_analyses', [
            'target_type' => 'post_queue',
            'target_id' => $post->id,
            'provider' => 'gemini',
            'score' => 92,
        ]);
    }

    public function test_post_scoring_uses_cache_if_valid(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'valid-key-xyz');

        $post = PostQueue::create([
            'caption' => 'Cached post text',
            'status' => 'draft',
        ]);

        // Create a pre-existing analysis (cache) created after the post was created
        $analysis = AiAnalysis::create([
            'target_type' => 'post_queue',
            'target_id' => $post->id,
            'provider' => 'gemini',
            'score' => 75,
            'result_json' => [
                'score' => 75,
                'strengths' => ['Cached strength'],
                'weaknesses' => ['Cached weakness'],
                'suggestions' => ['Cached suggestion'],
            ]
        ]);

        // Mock Http to verify NO API calls are made when cache is loaded
        Http::fake();

        $response = $this->post("/queue/{$post->id}/analyze");

        $response->assertRedirect('/queue');
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Loaded post analysis from cache', session('success'));

        // Verify no Http requests were made
        Http::assertNothingSent();
    }

    public function test_post_scoring_recaches_if_post_updated(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'valid-key-xyz');

        $post = PostQueue::create([
            'caption' => 'Original text',
            'status' => 'draft',
        ]);

        // Create cache record using query builder to bypass Eloquent timestamps auto-overwrite
        \Illuminate\Support\Facades\DB::table('ai_analyses')->insert([
            'target_type' => 'post_queue',
            'target_id' => $post->id,
            'provider' => 'gemini',
            'score' => 60,
            'result_json' => json_encode([
                'score' => 60,
                'strengths' => ['Old strength'],
                'weaknesses' => ['Old weakness'],
                'suggestions' => ['Old suggestion'],
            ]),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        // Sửa caption, Eloquent sẽ tự động cập nhật updated_at thành hiện tại (lớn hơn 10 phút trước)
        $post->update([
            'caption' => 'New modified text',
        ]);

        // Now mock Http call to return new analysis
        $jsonOutput = json_encode([
            'score' => 90,
            'strengths' => ['New strength'],
            'weaknesses' => ['New weakness'],
            'suggestions' => ['New suggestion']
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

        $response = $this->post("/queue/{$post->id}/analyze");

        $response->assertRedirect('/queue');
        $response->assertSessionHas('success');
        $this->assertStringContainsString('scored by AI successfully', session('success'));

        // Verify score is updated in database
        $this->assertDatabaseHas('ai_analyses', [
            'target_type' => 'post_queue',
            'target_id' => $post->id,
            'provider' => 'gemini',
            'score' => 90,
        ]);
    }
}
