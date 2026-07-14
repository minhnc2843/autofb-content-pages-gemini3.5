<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Models\MediaItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_month_and_year_validation_fallbacks(): void
    {
        Topic::create(['name' => 'Tech', 'keyword' => 'tech']);

        // Request with invalid month (99) and invalid year (5000)
        $response = $this->get('/calendar?month=99&year=5000');

        $response->assertStatus(200);

        // Fallbacks should reset month/year to current month/year
        $response->assertInertia(fn ($page) => $page
            ->component('Calendar/Index')
            ->where('month', (int)Carbon::now()->month)
            ->where('year', (int)Carbon::now()->year)
        );
    }

    public function test_calendar_status_and_topic_filter_all(): void
    {
        $topic = Topic::create(['name' => 'Tech', 'keyword' => 'tech']);

        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Post A',
            'status' => 'draft',
            'scheduled_at' => Carbon::now()->startOfMonth()->addDays(2)->setTime(8, 0),
        ]);

        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Post B',
            'status' => 'approved',
            'scheduled_at' => Carbon::now()->startOfMonth()->addDays(3)->setTime(13, 0),
        ]);

        // Request with status=all and topic_id=all
        $response = $this->get('/calendar?status=all&topic_id=all');

        $response->assertStatus(200);

        // Should return both posts (no filter applied)
        $response->assertInertia(fn ($page) => $page
            ->component('Calendar/Index')
            ->has('posts', 2)
        );
    }

    public function test_missing_slots_does_not_alert_for_past_days(): void
    {
        Topic::create(['name' => 'Tech', 'keyword' => 'tech']);

        // Create a past date (e.g. 5 days ago) inside the current month
        $pastDate = Carbon::yesterday()->subDays(2);
        
        // Start of month to today
        $response = $this->get('/calendar?month=' . Carbon::now()->month . '&year=' . Carbon::now()->year);

        $response->assertStatus(200);

        $missingSlots = $response->original->getData()['page']['props']['missingSlotsDates'] ?? [];

        // Assert that the past date is NOT in the missing slots warning array
        $this->assertNotContains($pastDate->format('Y-m-d'), $missingSlots);
    }
}
