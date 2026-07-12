<?php

namespace Tests\Unit;

use App\Services\CaptionService;
use PHPUnit\Framework\TestCase;

class CaptionServiceTest extends TestCase
{
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
}
