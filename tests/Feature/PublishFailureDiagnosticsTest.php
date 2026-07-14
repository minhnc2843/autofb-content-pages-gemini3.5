<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\PostPublishLog;
use App\Models\Setting;
use App\Services\FacebookPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishFailureDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. QueueEditPublishFailureDisplayTest
     */
    public function test_queue_edit_page_displays_failed_status_and_publish_logs(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test post failing caption',
            'status' => 'failed',
            'error_message' => 'Facebook API error: Some failure details (code: 190)',
            'publish_attempts' => 2,
        ]);

        $log = PostPublishLog::create([
            'post_queue_id' => $post->id,
            'mode' => 'real',
            'provider' => 'facebook',
            'action' => 'publish_feed',
            'status' => 'failed',
            'request_summary' => ['endpoint' => 'me/feed'],
            'response_json' => ['error' => ['message' => 'Some failure details']],
            'error_message' => 'Facebook API error: Some failure details (code: 190)',
        ]);

        $response = $this->get(route('queue.edit', $post));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Queue/Edit')
            ->has('post')
            ->where('post.id', $post->id)
            ->where('post.status', 'failed')
            ->where('post.error_message', 'Facebook API error: Some failure details (code: 190)')
            ->has('post.publish_logs')
            ->where('post.publish_logs.0.id', $log->id)
            ->where('post.publish_logs.0.error_message', $log->error_message)
        );
    }

    /**
     * 2. DebugPostPublishCommandTest
     */
    public function test_debug_post_publish_command_outputs_diagnostics(): void
    {
        $mediaItem = MediaItem::create([
            'pexels_id' => '123',
            'type' => 'photo',
            'url' => 'https://example.com/photo.jpg',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Test caption',
            'status' => 'approved',
            'scheduled_at' => now()->subDay(),
        ]);

        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        Http::fake([
            'https://example.com/*' => Http::response('ok', 200),
        ]);

        $this->artisan('posts:debug-publish', ['postId' => $post->id])
            ->expectsOutputToContain("Status: approved")
            ->expectsOutputToContain("FACEBOOK_PUBLISH_MODE: fake")
            ->expectsOutputToContain("Media Type: photo")
            ->assertExitCode(0);
    }

    /**
     * 3. FacebookGraphErrorParsingTest
     */
    public function test_facebook_graph_error_parsing(): void
    {
        $service = new FacebookPageService();
        
        $guzzleResponse = new \GuzzleHttp\Psr7\Response(400, [], json_encode([
            'error' => [
                'message' => 'Unsupported post request',
                'code' => 100,
                'error_subcode' => 33,
                'type' => 'OAuthException',
            ]
        ]));
        $fakeResponse = new \Illuminate\Http\Client\Response($guzzleResponse);

        $reflector = new \ReflectionClass(FacebookPageService::class);
        $method = $reflector->getMethod('parseGraphError');
        $method->setAccessible(true);

        $parsedMessage = $method->invoke($service, $fakeResponse);

        $this->assertStringContainsString('Unsupported post request', $parsedMessage);
        $this->assertStringContainsString('code: 100', $parsedMessage);
        $this->assertStringContainsString('subcode: 33', $parsedMessage);
        $this->assertStringContainsString('type: OAuthException', $parsedMessage);
    }

    /**
     * 4. RetryFailedPostTest
     */
    public function test_retry_failed_post_re_approves_and_clears_error(): void
    {
        $post = PostQueue::create([
            'caption' => 'Failed caption',
            'status' => 'failed',
            'error_message' => 'Previous error message details',
        ]);

        $response = $this->patch(route('queue.approve', $post));

        $response->assertRedirect(route('queue.index'));
        $post->refresh();
        $this->assertEquals('approved', $post->status);
        $this->assertNull($post->error_message);

        $history = $post->statusHistories()->latest()->first();
        $this->assertEquals('failed', $history->from_status);
        $this->assertEquals('approved', $history->to_status);
    }

    /**
     * 5. FakeModePublishNowTest
     */
    public function test_fake_mode_publish_now(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Caption to publish immediately',
            'status' => 'approved',
            'scheduled_at' => now(),
        ]);

        $response = $this->post(route('queue.publishNow', $post));

        $response->assertRedirect(route('queue.index'));
        $post->refresh();
        $this->assertEquals('published_fake', $post->status);
    }
}
