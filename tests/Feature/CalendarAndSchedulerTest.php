<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Models\MediaItem;
use App\Services\DuplicateProtectionService;
use App\Services\ContentCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_view_loads(): void
    {
        Topic::create(['name' => 'Tech', 'keyword' => 'tech']);

        $post = PostQueue::create([
            'caption' => 'Scheduled post',
            'status' => 'draft',
            'scheduled_at' => Carbon::now()->startOfMonth()->addDays(5)->setTime(13, 0)
        ]);

        $response = $this->get('/calendar?month=' . Carbon::now()->month . '&year=' . Carbon::now()->year);
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Calendar/Index')
            ->has('posts', 1)
            ->where('posts.0.caption', 'Scheduled post')
            ->has('missingSlotsDates')
        );
    }

    public function test_duplicate_protection_service(): void
    {
        $dupService = new DuplicateProtectionService();

        $media = MediaItem::create([
            'pexels_id' => '100',
            'type' => 'photo',
            'url' => 'https://pexels.com/100.jpg',
            'thumbnail_url' => 'https://pexels.com/100-thumb.jpg',
            'photographer' => 'Photographer'
        ]);

        $post = PostQueue::create([
            'caption' => 'Unique caption',
            'status' => 'draft',
            'media_item_id' => $media->id,
            'scheduled_at' => '2026-07-20 08:00:00'
        ]);

        // Duplicate media check
        $this->assertTrue($dupService->isDuplicateMedia($media->id));
        $this->assertFalse($dupService->isDuplicateMedia(99999));

        // Duplicate caption check
        $this->assertTrue($dupService->isDuplicateCaption('Unique caption'));
        $this->assertFalse($dupService->isDuplicateCaption('Completely different caption'));

        // Slot taken check
        $this->assertTrue($dupService->isSlotTaken('2026-07-20 08:00:00'));
        $this->assertFalse($dupService->isSlotTaken('2026-07-20 13:00:00'));
    }

    public function test_auto_schedule_generator(): void
    {
        $topic = Topic::create(['name' => 'Nature', 'keyword' => 'nature']);
        
        // Populate a media item
        MediaItem::create([
            'pexels_id' => '200',
            'type' => 'photo',
            'url' => 'https://pexels.com/nature.jpg',
            'thumbnail_url' => 'https://pexels.com/nature-thumb.jpg',
            'photographer' => 'Nature Photographer'
        ]);

        $scheduler = new ContentCalendarService();
        $generated = $scheduler->generateSchedule(3, 3); // 3 days, 3 posts/day = 9 posts

        $this->assertEquals(9, $generated);

        // Check that posts are saved in DB
        $this->assertDatabaseCount('posts_queue', 9);

        // Verify the slots (08:00, 13:00, 20:00) starting tomorrow
        $tomorrow = Carbon::tomorrow();
        $this->assertDatabaseHas('posts_queue', [
            'scheduled_at' => $tomorrow->copy()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'status' => 'draft'
        ]);
        $this->assertDatabaseHas('posts_queue', [
            'scheduled_at' => $tomorrow->copy()->setTime(13, 0)->format('Y-m-d H:i:s')
        ]);
        $this->assertDatabaseHas('posts_queue', [
            'scheduled_at' => $tomorrow->copy()->setTime(20, 0)->format('Y-m-d H:i:s')
        ]);
    }

    public function test_generate_schedule_via_artisan_command(): void
    {
        Topic::create(['name' => 'Nature', 'keyword' => 'nature']);
        MediaItem::create([
            'pexels_id' => '200',
            'type' => 'photo',
            'url' => 'https://pexels.com/nature.jpg',
            'thumbnail_url' => 'https://pexels.com/nature-thumb.jpg',
            'photographer' => 'Nature Photographer'
        ]);

        $this->artisan('posts:generate-calendar --days=2 --posts-per-day=2')
            ->expectsOutputToContain('Successfully generated 4 draft posts')
            ->assertExitCode(0);

        $this->assertDatabaseCount('posts_queue', 4);
    }
}
