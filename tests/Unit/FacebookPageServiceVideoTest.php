<?php

namespace Tests\Unit;

use App\Models\MediaItem;
use App\Models\PostPublishLog;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Services\FacebookPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPageServiceVideoTest extends TestCase
{
    use RefreshDatabase;

    private FacebookPageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FacebookPageService();
    }

    public function test_fake_mode_video_publish_does_not_call_http(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $mediaItem = MediaItem::create([
            'pexels_id' => '123',
            'type' => 'video',
            'url' => 'https://example.com/video.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Fake mode video post',
            'status' => 'approved',
        ]);

        Http::fake();

        $result = $this->service->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('fake', $result['mode']);
        $this->assertEquals('published_fake', $post->fresh()->status);
        $this->assertStringContainsString('fake_video', $post->fresh()->facebook_post_id);

        Http::assertNothingSent();
    }

    public function test_real_mode_video_calls_correct_endpoint_and_parameters(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/page_id_123/videos' => Http::response([
                'id' => 'video_id_999',
            ], 200),
            'https://example.com/video.mp4' => Http::response('', 200, ['Content-Length' => 5 * 1024 * 1024]), // Mock HEAD response
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => '456',
            'type' => 'video',
            'url' => 'https://example.com/video.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Real mode video post description',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('video_id_999', $post->fresh()->facebook_post_id);
        $this->assertEquals('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v25.0/page_id_123/videos' &&
                $request['file_url'] === 'https://example.com/video.mp4' &&
                $request['description'] === 'Real mode video post description' &&
                $request['access_token'] === 'token_123';
        });
    }

    public function test_real_mode_video_prefers_post_id(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'post_id' => 'page_post_id_abc',
                'id' => 'video_id_xyz',
            ], 200),
            'https://example.com/*' => Http::response('', 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => '789',
            'type' => 'video',
            'url' => 'https://example.com/video1.mp4',
            'thumbnail_url' => 'https://example.com/thumb1.jpg',
        ]);

        $post1 = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Post 1',
            'status' => 'approved',
        ]);

        $this->service->publishPost($post1);
        $this->assertEquals('page_post_id_abc', $post1->fresh()->facebook_post_id);
    }

    public function test_real_mode_video_fallback_to_id(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'video_id_111',
            ], 200),
            'https://example.com/*' => Http::response('', 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => '789',
            'type' => 'video',
            'url' => 'https://example.com/video1.mp4',
            'thumbnail_url' => 'https://example.com/thumb1.jpg',
        ]);

        $post2 = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Post 2',
            'status' => 'approved',
        ]);

        $this->service->publishPost($post2);
        $this->assertEquals('video_id_111', $post2->fresh()->facebook_post_id);
    }

    public function test_real_mode_video_fallback_to_video_id(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'video_id' => 'video_id_222',
            ], 200),
            'https://example.com/*' => Http::response('', 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => '789',
            'type' => 'video',
            'url' => 'https://example.com/video1.mp4',
            'thumbnail_url' => 'https://example.com/thumb1.jpg',
        ]);

        $post3 = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Post 3',
            'status' => 'approved',
        ]);

        $this->service->publishPost($post3);
        $this->assertEquals('video_id_222', $post3->fresh()->facebook_post_id);
    }

    public function test_real_mode_video_413_payload_too_large_or_size_limit_exceeded(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_VIDEO_MAX_MB', '10'); // 10MB limit

        // Mock HEAD response to return 15MB file size
        Http::fake([
            'https://example.com/large.mp4' => Http::response('', 200, ['Content-Length' => 15 * 1024 * 1024]),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => '000',
            'type' => 'video',
            'url' => 'https://example.com/large.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Large video',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertStringContainsString('limit of 10MB', $result['message']);
    }

    public function test_real_mode_video_api_error_sets_failed_and_logs(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'bad_token', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 401),
            'https://example.com/*' => Http::response('', 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => '111',
            'type' => 'video',
            'url' => 'https://example.com/video.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'OAuth error post',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertNotEmpty($post->fresh()->error_message);
        $this->assertEquals(1, $post->fresh()->publish_attempts);
    }

    public function test_real_mode_video_missing_media_item_fails(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        $post = PostQueue::create([
            'caption' => 'Post with no media',
            'status' => 'approved',
        ]);

        $result = $this->service->publishVideoPost($post);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('valid video media item', $result['message']);
    }

    public function test_real_mode_video_invalid_media_type_fails(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        $mediaItem = MediaItem::create([
            'pexels_id' => 'photo_id',
            'type' => 'photo',
            'url' => 'https://example.com/photo.jpg',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Post with photo',
            'status' => 'approved',
        ]);

        $result = $this->service->publishVideoPost($post);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('valid video media item', $result['message']);
    }

    public function test_token_not_logged_on_video_publish(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_leak_check_secret_999', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'vid_id'], 200),
            'https://example.com/*' => Http::response('', 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'v1',
            'type' => 'video',
            'url' => 'https://example.com/video.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Token check',
            'status' => 'approved',
        ]);

        $this->service->publishPost($post);

        $logs = PostPublishLog::where('action', 'publish_video')->get();
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $requestJson = json_encode($log->request_summary ?? []);
            $responseJson = json_encode($log->response_json ?? []);
            $this->assertStringNotContainsString('token_leak_check_secret_999', $requestJson);
            $this->assertStringNotContainsString('token_leak_check_secret_999', $responseJson);
        }
    }

    public function test_real_mode_video_local_download_upload(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', 'page_id_123');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'token_123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');
        Setting::setValue('FACEBOOK_VIDEO_UPLOAD_MODE', 'local_download');

        // Mock download response and upload response
        Http::fake([
            'https://example.com/video.mp4' => Http::response('fake_video_content_bytes', 200),
            'graph.facebook.com/v25.0/page_id_123/videos' => Http::response([
                'id' => 'video_id_local_777',
            ], 200),
        ]);

        $mediaItem = MediaItem::create([
            'pexels_id' => 'local_download_1',
            'type' => 'video',
            'url' => 'https://example.com/video.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Local download mode video post description',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('video_id_local_777', $post->fresh()->facebook_post_id);
        $this->assertEquals('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v25.0/page_id_123/videos' &&
                $request->isMultipart();
        });
    }
}
