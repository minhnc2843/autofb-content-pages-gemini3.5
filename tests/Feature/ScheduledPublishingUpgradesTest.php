<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPublishingUpgradesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        date_default_timezone_set('Asia/Ho_Chi_Minh');
    }

    /**
     * 1. ScheduledPublishTimezoneTest
     */
    public function test_scheduled_publish_timezone_due_filtering(): void
    {
        $pastPost = PostQueue::create([
            'caption' => 'Past post',
            'status' => 'approved',
            'scheduled_at' => now()->subMinutes(5),
        ]);

        $futurePost = PostQueue::create([
            'caption' => 'Future post',
            'status' => 'approved',
            'scheduled_at' => now()->addMinutes(5),
        ]);

        $this->assertEquals(1, PostQueue::due()->count());
        $this->assertEquals($pastPost->id, PostQueue::due()->first()->id);
    }

    /**
     * 2. DuePostPublisherServiceTest
     */
    public function test_due_post_publisher_service_publishes_correctly(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $pastApproved = PostQueue::create([
            'caption' => 'Approved past',
            'status' => 'approved',
            'scheduled_at' => now()->subMinutes(10),
        ]);

        $pastDraft = PostQueue::create([
            'caption' => 'Draft past',
            'status' => 'draft',
            'scheduled_at' => now()->subMinutes(10),
        ]);

        $futureApproved = PostQueue::create([
            'caption' => 'Approved future',
            'status' => 'approved',
            'scheduled_at' => now()->addMinutes(10),
        ]);

        $service = new \App\Services\DuePostPublisherService();
        $summary = $service->publishDuePosts(false);

        $this->assertEquals(1, $summary['found']);
        $this->assertEquals(1, $summary['published']);
        $this->assertEquals(0, $summary['failed']);
        $this->assertEquals('fake', $summary['mode']);

        $pastApproved->refresh();
        $pastDraft->refresh();
        $futureApproved->refresh();

        $this->assertEquals('published_fake', $pastApproved->status);
        $this->assertEquals('draft', $pastDraft->status);
        $this->assertEquals('approved', $futureApproved->status);
    }

    /**
     * 3. PublishDueNowRouteTest
     */
    public function test_publish_due_now_route_executes_successfully(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Due post',
            'status' => 'approved',
            'scheduled_at' => now()->subMinutes(15),
        ]);

        $response = $this->post(route('queue.publishDueNow'));

        $response->assertRedirect();
        $post->refresh();
        $this->assertEquals('published_fake', $post->status);

        $this->assertEquals(now()->toDateTimeString(), Setting::getValue('PUBLISH_DUE_LAST_RUN_AT'));
        $this->assertEquals('1', Setting::getValue('PUBLISH_DUE_LAST_FOUND'));
    }

    /**
     * 4. PublishNowStillWorksTest
     */
    public function test_publish_now_still_works(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $post = PostQueue::create([
            'caption' => 'Immediate publish post',
            'status' => 'approved',
            'scheduled_at' => now()->addHour(),
        ]);

        $response = $this->post(route('queue.publishNow', $post));

        $response->assertRedirect();
        $post->refresh();
        $this->assertEquals('published_fake', $post->status);
    }

    /**
     * 5. SchedulerHeartbeatTest
     */
    public function test_scheduler_heartbeat_settings_recorded_after_command(): void
    {
        Setting::setValue('FACEBOOK_PUBLISH_MODE', 'fake');

        PostQueue::create([
            'caption' => 'Past post to trigger command',
            'status' => 'approved',
            'scheduled_at' => now()->subMinutes(5),
        ]);

        $this->artisan('posts:publish-due')
            ->expectsOutputToContain('Found 1 due post(s).')
            ->assertExitCode(0);

        $this->assertNotEmpty(Setting::getValue('PUBLISH_DUE_LAST_RUN_AT'));
        $this->assertEquals('1', Setting::getValue('PUBLISH_DUE_LAST_FOUND'));
        $this->assertEquals('1', Setting::getValue('PUBLISH_DUE_LAST_PUBLISHED'));
        $this->assertEquals('0', Setting::getValue('PUBLISH_DUE_LAST_FAILED'));
    }

    /**
     * 6. DebugPublishCommandTest
     */
    public function test_debug_publish_command_outputs_timezone_diagnostics(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test debug post',
            'status' => 'approved',
            'scheduled_at' => now()->addMinutes(10),
        ]);

        $this->artisan('posts:debug-publish', ['postId' => $post->id])
            ->expectsOutputToContain('App Timezone: Asia/Ho_Chi_Minh')
            ->expectsOutputToContain('Current App Time:')
            ->expectsOutputToContain('Warning: This post is not due yet. Check timezone.')
            ->assertExitCode(0);
    }
}
