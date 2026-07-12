<?php

namespace Tests\Unit;

use App\Models\PostPublishLog;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Services\FacebookPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPageServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacebookPageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FacebookPageService();
    }

    public function test_missing_page_id_throws_clear_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Facebook Page ID is not configured');

        $this->service->getPageId();
    }

    public function test_missing_token_throws_clear_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Facebook Page Access Token is not configured');

        $this->service->getPageAccessToken();
    }

    public function test_fake_mode_does_not_call_http(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Test caption for fake publish',
            'status' => 'approved',
        ]);

        Http::fake(); // If any HTTP call is made, it will be recorded

        $result = $this->service->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('fake', $result['mode']);
        $this->assertEquals('published_fake', $post->fresh()->status);

        // Verify no HTTP calls were made
        Http::assertNothingSent();
    }

    public function test_mock_publish_text_success(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'test-token-123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/123456789/feed' => Http::response([
                'id' => '123456789_987654321',
            ], 200),
        ]);

        $post = PostQueue::create([
            'caption' => 'Hello from test!',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('123456789_987654321', $result['facebook_post_id']);
        $this->assertEquals('published', $post->fresh()->status);
        $this->assertEquals('123456789_987654321', $post->fresh()->facebook_post_id);
    }

    public function test_mock_publish_photo_success(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'test-token-123', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/123456789/photos' => Http::response([
                'id' => 'photo_111',
                'post_id' => '123456789_photo_111',
            ], 200),
        ]);

        $mediaItem = \App\Models\MediaItem::create([
            'pexels_id' => '99999',
            'type' => 'photo',
            'url' => 'https://images.pexels.com/photos/99999/original.jpg',
            'thumbnail_url' => 'https://images.pexels.com/photos/99999/small.jpg',
            'photographer' => 'Test Photographer',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Beautiful photo!',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertEquals('123456789_photo_111', $result['facebook_post_id']);
        $this->assertEquals('published', $post->fresh()->status);
    }

    public function test_api_error_sets_post_failed(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'invalid-token', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/123456789/feed' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $post = PostQueue::create([
            'caption' => 'This will fail',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertNotEmpty($post->fresh()->error_message);
    }

    public function test_token_not_logged(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'super-secret-token-xyz', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '123_456'], 200),
        ]);

        $post = PostQueue::create([
            'caption' => 'Token check',
            'status' => 'approved',
        ]);

        $this->service->publishPost($post);

        // Check all publish logs don't contain the token
        $logs = PostPublishLog::all();
        foreach ($logs as $log) {
            $requestJson = json_encode($log->request_summary ?? []);
            $responseJson = json_encode($log->response_json ?? []);
            $this->assertStringNotContainsString('super-secret-token-xyz', $requestJson);
            $this->assertStringNotContainsString('super-secret-token-xyz', $responseJson);
            $this->assertStringNotContainsString('super-secret-token-xyz', $log->error_message ?? '');
        }
    }

    public function test_video_post_returns_error(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'test-token', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        $mediaItem = \App\Models\MediaItem::create([
            'pexels_id' => '88888',
            'type' => 'video',
            'url' => 'https://videos.pexels.com/88888/hd.mp4',
            'thumbnail_url' => 'https://images.pexels.com/88888/poster.jpg',
            'photographer' => 'Video Person',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Video post test',
            'status' => 'approved',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Phase 2', $result['message']);
        $this->assertEquals('failed', $post->fresh()->status);
    }

    public function test_graph_base_url_default(): void
    {
        $url = $this->service->getGraphBaseUrl();
        $this->assertEquals('https://graph.facebook.com/v25.0', $url);
    }

    public function test_graph_base_url_custom(): void
    {
        Setting::setValue('META_GRAPH_VERSION', 'v20.0');
        $url = $this->service->getGraphBaseUrl();
        $this->assertEquals('https://graph.facebook.com/v20.0', $url);
    }

    public function test_validate_config_success(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'valid-token', true);

        Http::fake([
            'graph.facebook.com/v25.0/123456789*' => Http::response([
                'id' => '123456789',
                'name' => 'My Test Page',
                'link' => 'https://facebook.com/mytestpage',
            ], 200),
        ]);

        $result = $this->service->validateConfig();

        $this->assertTrue($result['success']);
        $this->assertEquals('123456789', $result['page_id']);
        $this->assertEquals('My Test Page', $result['page_name']);
    }

    public function test_validate_config_401(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'expired-token', true);

        Http::fake([
            'graph.facebook.com/v25.0/123456789*' => Http::response([
                'error' => ['message' => 'Invalid token', 'type' => 'OAuthException', 'code' => 190],
            ], 401),
        ]);

        $result = $this->service->validateConfig();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expired', strtolower($result['message']));
    }

    public function test_only_approved_posts_can_be_published_in_real_mode(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        $post = PostQueue::create([
            'caption' => 'Draft post',
            'status' => 'draft',
        ]);

        $result = $this->service->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('approved', $result['message']);
        $this->assertEquals('draft', $post->fresh()->status);
    }

    public function test_publish_log_created(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Log test',
            'status' => 'approved',
        ]);

        $this->service->publishPost($post);

        $this->assertDatabaseHas('post_publish_logs', [
            'post_queue_id' => $post->id,
            'mode' => 'fake',
            'provider' => 'facebook',
            'status' => 'success',
        ]);
    }
}
