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
}
