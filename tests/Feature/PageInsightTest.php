<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\PageInsight;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_insights_fake_mode(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        Http::fake();

        $response = $this->post('/insights/sync');

        $response->assertRedirect('/');
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Synced mock page insights successfully', session('success'));

        // Check that database has page insights records for all 3 metrics
        $this->assertDatabaseHas('page_insights', [
            'metric' => 'page_impressions',
            'period' => 'day',
        ]);
        $this->assertDatabaseHas('page_insights', [
            'metric' => 'page_post_engagements',
            'period' => 'day',
        ]);
        $this->assertDatabaseHas('page_insights', [
            'metric' => 'page_fans',
            'period' => 'lifetime',
        ]);

        Http::assertNothingSent();
    }

    public function test_sync_insights_real_mode_success(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/page_123/insights*' => Http::response([
                'data' => [
                    [
                        'name' => 'page_impressions',
                        'period' => 'day',
                        'values' => [
                            [
                                'value' => 1500,
                                'end_time' => '2026-07-13T07:00:00+0000',
                            ]
                        ]
                    ],
                    [
                        'name' => 'page_post_engagements',
                        'period' => 'day',
                        'values' => [
                            [
                                'value' => 250,
                                'end_time' => '2026-07-13T07:00:00+0000',
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->post('/insights/sync');

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('page_insights', [
            'metric' => 'page_impressions',
            'period' => 'day',
            'fetched_date' => '2026-07-13 00:00:00',
        ]);
        
        $insight = PageInsight::where('metric', 'page_impressions')->first();
        $this->assertEquals(1500, $insight->values_json['value']);
    }

    public function test_audit_fails_without_gemini_key(): void
    {
        Setting::setValue('GEMINI_API_KEY', '');

        $response = $this->post('/insights/audit');

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Gemini API key is not configured', session('error'));
    }

    public function test_audit_success(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'valid-gemini-key');

        $jsonOutput = json_encode([
            'page_score' => 85,
            'strengths' => ['Consistent post formatting', 'Good engagement rate'],
            'weaknesses' => ['Need more video reels'],
            'suggestions' => ['Post at least 2 reels per week.'],
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

        // Insert some mock insights
        PageInsight::create([
            'metric' => 'page_impressions',
            'period' => 'day',
            'values_json' => ['value' => 2000],
            'fetched_date' => '2026-07-13',
        ]);

        $response = $this->post('/insights/audit');

        $response->assertRedirect('/');
        $response->assertSessionHas('success');
        $this->assertStringContainsString('completed successfully', session('success'));

        $this->assertDatabaseHas('ai_analyses', [
            'target_type' => 'page_insight',
            'target_id' => 0,
            'provider' => 'gemini',
            'score' => 85,
        ]);
    }
}
