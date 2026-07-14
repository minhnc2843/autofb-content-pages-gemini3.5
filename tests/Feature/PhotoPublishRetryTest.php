<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Services\FacebookPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhotoPublishRetryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 2. FacebookPageServicePhotoRetryTest
     */
    public function test_photo_publish_retries_compressed_when_code_1_error_occurs(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_PAGE_ID', '123456');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token-abc');

        $mediaItem = MediaItem::create([
            'pexels_id' => '111',
            'type' => 'photo',
            'url' => 'https://images.pexels.com/photos/111/original.jpg',
            'thumbnail_url' => 'https://images.pexels.com/photos/111/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Test caption',
            'status' => 'approved',
            'scheduled_at' => now(),
        ]);

        Http::fake([
            'graph.facebook.com/*/photos' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => "Please reduce the amount of data you're asking for, then retry your request",
                        'code' => 1,
                        'type' => 'OAuthException',
                    ]
                ], 400)
                ->push([
                    'id' => 'fb_photo_9999',
                ], 200),
        ]);

        $service = new FacebookPageService();
        $result = $service->publishPhotoPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('fb_photo_9999', $result['facebook_post_id']);
        $this->assertEquals('Photo post published successfully after compressed retry.', $result['message']);

        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return str_contains($data['url'] ?? '', 'w=1600');
        });

        Http::assertSent(function ($request) {
            $data = $request->data();
            return str_contains($data['url'] ?? '', 'w=1200');
        });
    }

    /**
     * 3. MediaOptimizePexelsUrlsCommandTest
     */
    public function test_media_optimize_pexels_urls_command_updates_urls(): void
    {
        $photo = MediaItem::create([
            'pexels_id' => '100',
            'type' => 'photo',
            'url' => 'https://images.pexels.com/photos/100/original.jpg?some=param',
            'thumbnail_url' => 'https://images.pexels.com/photos/100/thumb.jpg',
        ]);

        $video = MediaItem::create([
            'pexels_id' => '200',
            'type' => 'video',
            'url' => 'https://videos.pexels.com/200/video.mp4',
            'thumbnail_url' => 'https://images.pexels.com/videos/200/poster.jpg',
        ]);

        $this->artisan('media:optimize-pexels-urls')
            ->expectsOutputToContain('Found 1 Pexels photo(s) to optimize.')
            ->expectsOutputToContain('Successfully optimized 1 Pexels photo(s).')
            ->assertExitCode(0);

        $photo->refresh();
        $video->refresh();

        $this->assertEquals('https://images.pexels.com/photos/100/original.jpg?auto=compress&cs=tinysrgb&w=1600', $photo->url);
        $this->assertEquals('https://images.pexels.com/photos/100/thumb.jpg?auto=compress&cs=tinysrgb&w=600', $photo->thumbnail_url);
        
        $this->assertEquals('https://videos.pexels.com/200/video.mp4', $video->url);
    }
}
