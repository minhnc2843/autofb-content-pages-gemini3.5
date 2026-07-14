<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\AI\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeminiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('GEMINI_ENABLED', 'true');
        $this->service = new GeminiService();
    }

    public function test_missing_api_key_returns_error_array(): void
    {
        Setting::setValue('GEMINI_API_KEY', '');

        // No key set in settings
        $result = $this->service->generateText('Hello Gemini');
        
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not configured', $result['error']);
    }

    public function test_gemini_generates_text_success(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'fake-gemini-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'This is a generated caption from AI.']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $result = $this->service->generateText('Write a caption about nature');

        $this->assertArrayHasKey('text', $result);
        $this->assertEquals('This is a generated caption from AI.', $result['text']);
        $this->assertArrayHasKey('raw_response', $result);
    }

    public function test_gemini_scores_post_and_returns_structured_json(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'fake-gemini-key');

        $jsonOutput = json_encode([
            'score' => 88,
            'strengths' => ['Good credit mention', 'Clear hook'],
            'weaknesses' => ['No hashtags'],
            'suggestions' => ['Add 3 hashtags at the end.']
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

        $postData = [
            'caption' => 'A beautiful day in paradise. Photo by John Doe.',
            'media' => [
                'type' => 'photo',
                'photographer' => 'John Doe',
            ]
        ];

        $result = $this->service->scorePost($postData);

        $this->assertArrayHasKey('score', $result);
        $this->assertEquals(88, $result['score']);
        $this->assertCount(2, $result['strengths']);
        $this->assertEquals('No hashtags', $result['weaknesses'][0]);
    }

    public function test_gemini_api_error_returns_error_details(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'fake-gemini-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'API key not valid.',
                    'status' => 'INVALID_ARGUMENT'
                ]
            ], 400),
        ]);

        $result = $this->service->generateText('Hello');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API key not valid', $result['error']);
    }

    public function test_gemini_analyzes_media_successfully(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'fake-gemini-key');

        $jsonOutput = json_encode([
            'media_score' => 95,
            'topic_fit' => 90,
            'visual_quality' => 95,
            'risk_level' => 'low',
            'suggestions' => ['Perfect for summer campaign']
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

        $mediaData = ['type' => 'photo', 'photographer' => 'Jane Doe', 'url' => 'https://pexels.com/1.jpg'];
        $result = $this->service->analyzeMedia($mediaData, 'summer vacation');

        $this->assertArrayHasKey('media_score', $result);
        $this->assertEquals(95, $result['media_score']);
        $this->assertEquals('low', $result['risk_level']);
    }

    public function test_gemini_audits_page_successfully(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'fake-gemini-key');

        $jsonOutput = json_encode([
            'page_score' => 78,
            'brand_score' => 80,
            'content_score' => 75,
            'cta_score' => 70,
            'consistency_score' => 85,
            'strengths' => ['Consistent visual style'],
            'weaknesses' => ['Low video post count'],
            'suggestions' => ['Create 2 reels weekly']
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

        $pageData = [
            'insights' => ['page_impressions' => 1500],
            'topics' => [['name' => 'Tech', 'is_active' => true]],
            'recent_logs' => [['status' => 'published', 'caption' => 'Hello World']]
        ];

        $result = $this->service->auditPage($pageData);

        $this->assertArrayHasKey('page_score', $result);
        $this->assertEquals(78, $result['page_score']);
        $this->assertEquals('Consistent visual style', $result['strengths'][0]);
    }

    public function test_gemini_generates_strategy_successfully(): void
    {
        Setting::setValue('GEMINI_API_KEY', 'fake-gemini-key');

        $jsonOutput = json_encode([
            'strategy_title' => 'Winter Theme Strategy',
            'overview' => 'Strategy targeting winter warmth.',
            'daily_plan' => [
                ['day' => 'Day 1', 'category' => 'educational', 'focus' => 'Warm clothing tips', 'prompt_suggestion' => 'Post a tip.']
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

        $topicsList = [['name' => 'Winter', 'keyword' => 'snow']];
        $result = $this->service->generateStrategy($topicsList);

        $this->assertArrayHasKey('strategy_title', $result);
        $this->assertEquals('Winter Theme Strategy', $result['strategy_title']);
        $this->assertEquals('educational', $result['daily_plan'][0]['category']);
    }
}
