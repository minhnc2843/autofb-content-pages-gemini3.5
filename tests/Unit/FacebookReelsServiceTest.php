<?php

namespace Tests\Unit;

use App\Models\MediaItem;
use App\Models\PostPublishLog;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Services\FacebookReelsService;
use App\Services\FacebookPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookReelsServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacebookReelsService $reelsService;
    private FacebookPageService $pageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reelsService = new FacebookReelsService();
        $this->pageService = new FacebookPageService();
    }

    public function test_fake_mode_reel_publish(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');
        Setting::setValue('FACEBOOK_PUBLISH_AS_REEL', 'true');

        $mediaItem = MediaItem::create([
            'pexels_id' => 'reel_fake_1',
            'type' => 'video',
            'url' => 'https://example.com/reel.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Fake Reel',
            'status' => 'approved',
        ]);

        Http::fake();

        $result = $this->pageService->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('fake', $result['mode']);
        $this->assertEquals('published_fake', $post->fresh()->status);
        $this->assertStringContainsString('fake_reel', $post->fresh()->facebook_post_id);

        Http::assertNothingSent();
    }

    public function test_real_mode_reel_publish_success(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_PUBLISH_AS_REEL', 'true');

        Http::fake([
            'https://example.com/reel.mp4' => Http::response('fake_video_bytes', 200),
            'graph.facebook.com/v25.0/page_id_123/video_reels' => Http::sequence()
                ->push([
                    'video_id' => 'reel_video_999',
                    'upload_url' => 'https://rupload.facebook.com/video-upload/reel_video_999',
                ], 200) // Initialize response
                ->push([
                    'success' => true,
                    'fb_id' => 'fb_reel_post_id_abc',
                ], 200), // Finish response
            'rupload.facebook.com/*' => Http::response(['success' => true], 200), // Upload bytes response
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'reel_real_1',
            'type' => 'video',
            'url' => 'https://example.com/reel.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Real Reel Caption',
            'status' => 'approved',
        ]);

        $result = $this->pageService->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('fb_reel_post_id_abc', $post->fresh()->facebook_post_id);
        $this->assertEquals('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://graph.facebook.com/v25.0/page_id_123/video_reels' && $request['upload_phase'] === 'initialize') {
                return $request['access_token'] === 'token_123';
            }
            if ($request->url() === 'https://rupload.facebook.com/video-upload/reel_video_999') {
                return $request->body() === 'fake_video_bytes' &&
                    $request->header('Authorization')[0] === 'OAuth token_123' &&
                    $request->header('file_size')[0] === '16'; // 'fake_video_bytes' size
            }
            if ($request->url() === 'https://graph.facebook.com/v25.0/page_id_123/video_reels' && $request['upload_phase'] === 'finish') {
                return $request['video_id'] === 'reel_video_999' &&
                    $request['video_state'] === 'PUBLISHED' &&
                    $request['description'] === 'Real Reel Caption' &&
                    $request['access_token'] === 'token_123';
            }
            return true;
        });
    }

    public function test_real_mode_reel_publish_size_limit(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_PUBLISH_AS_REEL', 'true');
        Setting::setValue('FACEBOOK_VIDEO_MAX_MB', '5'); // 5MB limit

        Http::fake([
            'https://example.com/large-reel.mp4' => Http::response('', 200, ['Content-Length' => 10 * 1024 * 1024]), // Mock 10MB
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'reel_large',
            'type' => 'video',
            'url' => 'https://example.com/large-reel.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Large Reel',
            'status' => 'approved',
        ]);

        $result = $this->pageService->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertStringContainsString('limit of 5MB', $result['message']);
    }

    public function test_real_mode_reel_publish_init_fails(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_PUBLISH_AS_REEL', 'true');

        Http::fake([
            'https://example.com/reel.mp4' => Http::response('fake_video_bytes', 200),
            'graph.facebook.com/v25.0/page_id_123/video_reels' => Http::response([
                'error' => [
                    'message' => 'The parameter upload_phase is required.',
                ]
            ], 400),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'reel_err_1',
            'type' => 'video',
            'url' => 'https://example.com/reel.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Fail Reel Init',
            'status' => 'approved',
        ]);

        $result = $this->pageService->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertStringContainsString('The parameter upload_phase is required.', $result['message']);
    }

    public function test_real_mode_reel_publish_upload_fails(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_PUBLISH_AS_REEL', 'true');

        Http::fake([
            'https://example.com/reel.mp4' => Http::response('fake_video_bytes', 200),
            'graph.facebook.com/v25.0/page_id_123/video_reels' => Http::response([
                'video_id' => 'reel_video_999',
                'upload_url' => 'https://rupload.facebook.com/video-upload/reel_video_999',
            ], 200),
            'rupload.facebook.com/*' => Http::response([
                'error' => ['message' => 'Upload connection interrupted.']
            ], 500),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'reel_err_2',
            'type' => 'video',
            'url' => 'https://example.com/reel.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Fail Reel Upload',
            'status' => 'approved',
        ]);

        $result = $this->pageService->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertStringContainsString('Upload connection interrupted.', $result['message']);
    }

    public function test_token_not_logged_on_reel_publish(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_leak_check_reel_secret', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_PUBLISH_AS_REEL', 'true');

        Http::fake([
            'https://example.com/reel.mp4' => Http::response('fake_video_bytes', 200),
            'graph.facebook.com/v25.0/page_id_123/video_reels' => Http::sequence()
                ->push([
                    'video_id' => 'reel_video_999',
                    'upload_url' => 'https://rupload.facebook.com/video-upload/reel_video_999',
                ], 200)
                ->push([
                    'success' => true,
                    'fb_id' => 'fb_reel_post_id_abc',
                ], 200),
            'rupload.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'v_reel',
            'type' => 'video',
            'url' => 'https://example.com/reel.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Reel token check',
            'status' => 'approved',
        ]);

        $this->pageService->publishPost($post);

        $logs = PostPublishLog::where('action', 'publish_reel')->get();
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $requestJson = json_encode($log->request_summary ?? []);
            $responseJson = json_encode($log->response_json ?? []);
            $this->assertStringNotContainsString('token_leak_check_reel_secret', $requestJson);
            $this->assertStringNotContainsString('token_leak_check_reel_secret', $responseJson);
        }
    }
}
