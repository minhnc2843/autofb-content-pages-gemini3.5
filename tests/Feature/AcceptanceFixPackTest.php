<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AcceptanceFixPackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test PexelsCreateDraftRedirectTest:
     * POST /pexels/create-draft creates post, redirects to edit page.
     */
    public function test_pexels_create_draft_creates_post_and_redirects_to_edit_page(): void
    {
        Setting::setValue('PEXELS_API_KEY', 'fake-key');

        $mediaData = [
            'pexels_id' => '123456',
            'type' => 'photo',
            'url' => 'https://pexels.com/photo/123456',
            'thumbnail_url' => 'https://pexels.com/photo/123456/thumb.jpg',
            'width' => 1920,
            'height' => 1080,
            'photographer' => 'Jane Photographer',
        ];

        $response = $this->post('/pexels/create-draft', [
            'media' => $mediaData,
        ]);

        $post = PostQueue::first();
        $this->assertNotNull($post);
        $this->assertEquals('draft', $post->status);
        $this->assertNotNull($post->scheduled_at);

        $response->assertRedirect(route('queue.edit', $post));
        $response->assertSessionHas('success');
    }

    /**
     * Test QueuePageSafetyTest:
     * GET /queue when empty does not throw exceptions and returns correct Inertia array posts.data.
     */
    public function test_queue_index_page_safety_when_empty(): void
    {
        $response = $this->get('/queue');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Queue/Index')
            ->has('posts')
            ->has('posts.data')
            ->where('posts.data', [])
        );
    }

    public function test_queue_index_page_returns_posts_data_when_exists(): void
    {
        $mediaItem = MediaItem::create([
            'pexels_id' => '999',
            'type' => 'photo',
            'url' => 'https://example.com/photo',
            'thumbnail_url' => 'https://example.com/thumb',
        ]);

        PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Test Post Caption',
            'status' => 'draft',
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->get('/queue');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Queue/Index')
            ->has('posts.data', 1)
            ->where('posts.data.0.caption', 'Test Post Caption')
        );
    }

    /**
     * Test PublishDueRequiresApprovedTest:
     * Draft posts will not publish, only approved due posts publish.
     */
    public function test_publish_due_command_ignores_draft_posts(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $mediaItem = MediaItem::create([
            'pexels_id' => '999',
            'type' => 'photo',
            'url' => 'https://example.com/photo',
            'thumbnail_url' => 'https://example.com/thumb',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Draft post too old',
            'status' => 'draft',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due')->assertExitCode(0);

        $post->refresh();
        $this->assertEquals('draft', $post->status);
    }

    public function test_publish_due_command_publishes_approved_due_posts_in_fake_mode(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $mediaItem = MediaItem::create([
            'pexels_id' => '999',
            'type' => 'photo',
            'url' => 'https://example.com/photo',
            'thumbnail_url' => 'https://example.com/thumb',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Approved post too old',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('posts:publish-due')->assertExitCode(0);

        $post->refresh();
        $this->assertEquals('published_fake', $post->status);
    }

    /**
     * Test SchedulerRegistrationTest:
     * verify routes/console.php registers publish-due scheduler.
     */
    public function test_scheduler_registers_publish_due_command_every_minute(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $hasCommand = $events->contains(function ($event) {
            return str_contains($event->command, 'posts:publish-due') && $event->expression === '* * * * *';
        });

        $this->assertTrue($hasCommand, 'posts:publish-due is not registered to run every minute in Scheduler.');
    }
}
