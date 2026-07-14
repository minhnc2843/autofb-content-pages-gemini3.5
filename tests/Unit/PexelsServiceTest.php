<?php

namespace Tests\Unit;

use App\Services\PexelsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PexelsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_api_key_throws_clear_error(): void
    {
        \App\Models\Setting::setValue('PEXELS_API_KEY', '');

        // Ensure no API key is set
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pexels API key is not configured');

        $service = new PexelsService();
        $service->searchPhotos('nature');
    }

    public function test_mock_api_response_parse_photo(): void
    {
        // Set a fake API key in settings
        \App\Models\Setting::setValue('PEXELS_API_KEY', 'test-key-123');

        Http::fake([
            'api.pexels.com/v1/search*' => Http::response([
                'total_results' => 1,
                'photos' => [
                    [
                        'id' => 12345,
                        'width' => 1920,
                        'height' => 1080,
                        'url' => 'https://www.pexels.com/photo/12345/',
                        'photographer' => 'John Doe',
                        'photographer_url' => 'https://www.pexels.com/@johndoe',
                        'src' => [
                            'original' => 'https://images.pexels.com/photos/12345/original.jpg',
                            'large' => 'https://images.pexels.com/photos/12345/large.jpg',
                            'medium' => 'https://images.pexels.com/photos/12345/medium.jpg',
                            'small' => 'https://images.pexels.com/photos/12345/small.jpg',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new PexelsService();
        $result = $service->searchPhotos('nature');

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('12345', $result['data'][0]['pexels_id']);
        $this->assertEquals('photo', $result['data'][0]['type']);
        $this->assertEquals('https://images.pexels.com/photos/12345/large.jpg?auto=compress&cs=tinysrgb&w=1600', $result['data'][0]['url']);
        $this->assertEquals('https://images.pexels.com/photos/12345/medium.jpg?auto=compress&cs=tinysrgb&w=600', $result['data'][0]['thumbnail_url']);
        $this->assertEquals('John Doe', $result['data'][0]['photographer']);
        $this->assertEquals(1920, $result['data'][0]['width']);
    }

    public function test_mock_api_response_parse_video(): void
    {
        \App\Models\Setting::setValue('PEXELS_API_KEY', 'test-key-123');

        Http::fake([
            'api.pexels.com/videos/search*' => Http::response([
                'total_results' => 1,
                'videos' => [
                    [
                        'id' => 67890,
                        'width' => 1920,
                        'height' => 1080,
                        'duration' => 30,
                        'url' => 'https://www.pexels.com/video/67890/',
                        'image' => 'https://images.pexels.com/videos/67890/poster.jpg',
                        'user' => [
                            'name' => 'Jane Doe',
                            'url' => 'https://www.pexels.com/@janedoe',
                        ],
                        'video_files' => [
                            [
                                'id' => 1,
                                'quality' => 'hd',
                                'link' => 'https://videos.pexels.com/67890/hd.mp4',
                                'width' => 1920,
                                'height' => 1080,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new PexelsService();
        $result = $service->searchVideos('sunset');

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('67890', $result['data'][0]['pexels_id']);
        $this->assertEquals('video', $result['data'][0]['type']);
        $this->assertEquals('Jane Doe', $result['data'][0]['photographer']);
        $this->assertEquals(30, $result['data'][0]['duration']);
    }

    public function test_api_returns_401_error(): void
    {
        \App\Models\Setting::setValue('PEXELS_API_KEY', 'invalid-key');

        Http::fake([
            'api.pexels.com/v1/search*' => Http::response([], 401),
        ]);

        $service = new PexelsService();
        $result = $service->searchPhotos('nature');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid Pexels API key', $result['error']);
    }

    public function test_video_parsing_prefers_mp4_under_1080p(): void
    {
        \App\Models\Setting::setValue('PEXELS_API_KEY', 'test-key-123');

        Http::fake([
            'api.pexels.com/videos/search*' => Http::response([
                'total_results' => 1,
                'videos' => [
                    [
                        'id' => 67890,
                        'width' => 3840,
                        'height' => 2160,
                        'duration' => 30,
                        'url' => 'https://www.pexels.com/video/67890/',
                        'image' => 'https://images.pexels.com/videos/67890/poster.jpg',
                        'user' => ['name' => 'Jane Doe'],
                        'video_files' => [
                            [
                                'id' => 1,
                                'quality' => '4k',
                                'file_type' => 'video/mp4',
                                'link' => 'https://videos.pexels.com/67890/4k.mp4',
                                'width' => 3840,
                                'height' => 2160,
                            ],
                            [
                                'id' => 2,
                                'quality' => 'hd',
                                'file_type' => 'video/mp4',
                                'link' => 'https://videos.pexels.com/67890/hd.mp4',
                                'width' => 1920,
                                'height' => 1080,
                            ],
                            [
                                'id' => 3,
                                'quality' => 'sd',
                                'file_type' => 'video/webm',
                                'link' => 'https://videos.pexels.com/67890/sd.webm',
                                'width' => 640,
                                'height' => 360,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new PexelsService();
        $result = $service->searchVideos('sunset');

        $this->assertEquals('https://videos.pexels.com/67890/hd.mp4', $result['data'][0]['url']);
        $this->assertEquals('https://images.pexels.com/videos/67890/poster.jpg', $result['data'][0]['thumbnail_url']);
    }
}
