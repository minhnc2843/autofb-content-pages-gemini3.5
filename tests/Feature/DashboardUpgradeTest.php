<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_upgraded_stats(): void
    {
        Topic::create(['name' => 'Tech', 'keyword' => 'tech']);

        // 1. Scheduled today
        PostQueue::create([
            'caption' => 'Post today',
            'status' => 'approved',
            'scheduled_at' => Carbon::today()->setTime(13, 0)
        ]);

        // 2. Scheduled tomorrow (part of next 7 days)
        PostQueue::create([
            'caption' => 'Post tomorrow',
            'status' => 'approved',
            'scheduled_at' => Carbon::tomorrow()->setTime(8, 0)
        ]);

        // 3. Failed post needing attention
        PostQueue::create([
            'caption' => 'Failed post 1',
            'status' => 'failed',
            'error_message' => 'Oauth Error'
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('extraStats')
            ->where('extraStats.scheduled_today', 1)
            ->where('extraStats.missing_slots_7_days', 7) // all 7 days have <3 posts, so 7 days are missing slots
            ->where('extraStats.coverage_percent', 4) // 1 post / 21 slots = 4.76% (rounded to 4)
            ->has('extraStats.next_scheduled_posts', 2)
            ->has('extraStats.failed_posts', 1)
        );
    }
}
