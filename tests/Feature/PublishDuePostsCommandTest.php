<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishDuePostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_publish_draft(): void
    {
        $post = PostQueue::create([
            'caption' => 'Draft post',
            'status' => 'draft',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due')
            ->assertSuccessful();

        $this->assertEquals('draft', $post->fresh()->status);
    }

    public function test_does_not_publish_approved_not_due(): void
    {
        $post = PostQueue::create([
            'caption' => 'Future post',
            'status' => 'approved',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->artisan('posts:publish-due')
            ->assertSuccessful();

        $this->assertEquals('approved', $post->fresh()->status);
    }

    public function test_fake_mode_publishes_due_as_fake(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Due post for fake',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due')
            ->assertSuccessful();

        $this->assertEquals('published_fake', $post->fresh()->status);

        $this->assertDatabaseHas('post_publish_logs', [
            'post_queue_id' => $post->id,
            'mode' => 'fake',
            'status' => 'success',
        ]);
    }

    public function test_real_mode_publishes_via_facebook(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'valid-token', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/123456789/feed' => Http::response([
                'id' => '123456789_realpost1',
            ], 200),
        ]);

        $post = PostQueue::create([
            'caption' => 'Real publish test',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due');

        $this->assertEquals('published', $post->fresh()->status);
        $this->assertEquals('123456789_realpost1', $post->fresh()->facebook_post_id);

        $this->assertDatabaseHas('post_publish_logs', [
            'post_queue_id' => $post->id,
            'mode' => 'real',
            'action' => 'publish_text',
            'status' => 'success',
        ]);
    }

    public function test_api_error_sets_failed(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'bad-token', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/123456789/feed' => Http::response([
                'error' => ['message' => 'Token expired', 'type' => 'OAuthException', 'code' => 190],
            ], 401),
        ]);

        $post = PostQueue::create([
            'caption' => 'Will fail',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due');

        $this->assertEquals('failed', $post->fresh()->status);
        $this->assertNotEmpty($post->fresh()->error_message);
    }

    public function test_multiple_due_posts(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post1 = PostQueue::create([
            'caption' => 'Due post 1',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);
        $post2 = PostQueue::create([
            'caption' => 'Due post 2',
            'status' => 'approved',
            'scheduled_at' => now()->subMinutes(30),
        ]);
        $post3 = PostQueue::create([
            'caption' => 'Draft not due',
            'status' => 'draft',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due');

        $this->assertEquals('published_fake', $post1->fresh()->status);
        $this->assertEquals('published_fake', $post2->fresh()->status);
        $this->assertEquals('draft', $post3->fresh()->status);
    }

    public function test_real_mode_photo_publish(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'valid-token', true);
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'real');

        Http::fake([
            'graph.facebook.com/v25.0/123456789/photos' => Http::response([
                'id' => 'photo_999',
                'post_id' => '123456789_photo_999',
            ], 200),
        ]);

        $media = MediaItem::create([
            'pexels_id' => '55555',
            'type' => 'photo',
            'url' => 'https://images.pexels.com/photos/55555/original.jpg',
            'thumbnail_url' => 'https://images.pexels.com/photos/55555/small.jpg',
            'photographer' => 'Photo Person',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $media->id,
            'caption' => 'Photo publish test',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due');

        $this->assertEquals('published', $post->fresh()->status);
        $this->assertEquals('123456789_photo_999', $post->fresh()->facebook_post_id);
    }

    public function test_publish_now_route(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Publish now test',
            'status' => 'approved',
        ]);

        $response = $this->post("/queue/{$post->id}/publish-now");

        $response->assertRedirect('/queue');
        $this->assertEquals('published_fake', $post->fresh()->status);
    }

    public function test_publish_now_rejects_draft(): void
    {
        $post = PostQueue::create([
            'caption' => 'Draft post',
            'status' => 'draft',
        ]);

        $response = $this->post("/queue/{$post->id}/publish-now");

        $response->assertRedirect('/queue');
        $response->assertSessionHas('error');
        $this->assertEquals('draft', $post->fresh()->status);
    }
}
