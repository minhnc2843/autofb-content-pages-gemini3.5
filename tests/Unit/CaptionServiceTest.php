<?php

namespace Tests\Unit;

use App\Services\CaptionService;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CaptionService();
    }

    public function test_generate_caption_english(): void
    {
        $topic = ['name' => 'Sunset', 'keyword' => 'sunset'];
        $media = ['type' => 'photo', 'photographer' => 'John Doe'];

        $caption = $this->service->generate($topic, $media, 'english');

        $this->assertNotEmpty($caption);
        $this->assertStringContainsString('sunset', strtolower($caption));
        $this->assertStringContainsString('#sunset', $caption);
    }

    public function test_generate_caption_vietnamese(): void
    {
        $topic = ['name' => 'Biển', 'keyword' => 'ocean'];
        $media = ['type' => 'photo', 'photographer' => 'Nguyen Van A'];

        $caption = $this->service->generate($topic, $media, 'vietnamese');

        $this->assertNotEmpty($caption);
        $this->assertStringContainsString('#ocean', $caption);
        // Vietnamese captions should contain Vietnamese text
        $this->assertMatchesRegularExpression('/[\x{0080}-\x{FFFF}]/u', $caption);
    }

    public function test_fallback_language(): void
    {
        $topic = ['name' => 'Mountain', 'keyword' => 'mountain'];
        $media = ['type' => 'video', 'photographer' => 'Jane Doe'];

        // Use an unsupported language - should fallback to english
        $caption = $this->service->generate($topic, $media, 'japanese');

        $this->assertNotEmpty($caption);
        $this->assertStringContainsString('mountain', strtolower($caption));
    }

    public function test_caption_contains_hashtags(): void
    {
        $topic = ['name' => 'Nature', 'keyword' => 'nature'];
        $media = ['type' => 'photo', 'photographer' => 'Photographer'];

        $caption = $this->service->generate($topic, $media, 'english');

        $this->assertStringContainsString('#nature', $caption);
        $this->assertStringContainsString('#pexels', $caption);
    }

    public function test_caption_contains_photographer(): void
    {
        $topic = ['name' => 'City', 'keyword' => 'city'];
        $media = ['type' => 'photo', 'photographer' => 'John Smith'];

        $caption = $this->service->generate($topic, $media, 'english');

        $this->assertStringContainsString('John Smith', $caption);
    }

    public function test_generate_with_ai_fallback_on_empty_api_key(): void
    {
        // Ensure no key is set
        Setting::setValue('GEMINI_API_KEY', null);

        $topic = ['name' => 'Forest', 'keyword' => 'forest'];
        $media = ['type' => 'photo', 'photographer' => 'Jane Doe'];

        // Should fallback to template-based generation
        $caption = $this->service->generateWithAi($topic, $media, 'english');

        $this->assertNotEmpty($caption);
        $this->assertStringContainsString('forest', strtolower($caption));
        $this->assertStringContainsString('Jane Doe', $caption);
    }

    public function test_generate_with_ai_calls_gemini_and_returns_caption(): void
    {
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'valid-ai-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'This is a creative caption written by AI. Credit Jane Doe.']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $topic = ['name' => 'Forest', 'keyword' => 'forest'];
        $media = ['type' => 'photo', 'photographer' => 'Jane Doe'];

        $caption = $this->service->generateWithAi($topic, $media, 'english', 'creative');

        $this->assertEquals('This is a creative caption written by AI. Credit Jane Doe.', $caption);
    }
}
