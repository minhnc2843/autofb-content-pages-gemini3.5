<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Topic;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Services\ContentCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentCalendarServiceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_only_creates_draft_posts_and_does_not_approve(): void
    {
        Topic::create(['name' => 'Tech', 'keyword' => 'developer', 'is_active' => true]);
        
        // Create 7 unique media items in cache to allow 7 days generation
        for ($i = 1; $i <= 7; $i++) {
            MediaItem::create([
                'pexels_id' => (string)(100 + $i),
                'type' => 'photo',
                'url' => "https://pexels.com/{$i}.jpg",
                'thumbnail_url' => "https://pexels.com/{$i}-thumb.jpg",
                'photographer' => 'Developer Photographer'
            ]);
        }

        $service = new ContentCalendarService();
        $summary = $service->generateScheduleForDays(7, ['posts_per_day' => 1]);

        $this->assertEquals(7, $summary['created']);
        $this->assertEquals(0, $summary['failed']);

        // Assert created posts are drafts
        $this->assertDatabaseHas('posts_queue', ['status' => 'draft']);
        $this->assertDatabaseMissing('posts_queue', ['status' => 'approved']);
    }

    public function test_scheduler_uses_pexels_api_fake_when_cache_is_insufficient(): void
    {
        Setting::setValue('PEXELS_API_KEY', 'valid-pexels-key');
        
        $topic = Topic::create(['name' => 'Cats', 'keyword' => 'kitty', 'is_active' => true]);

        // Fake Pexels API response
        Http::fake([
            'api.pexels.com/v1/search*' => Http::response([
                'photos' => [
                    [
                        'id' => 200,
                        'width' => 600,
                        'height' => 400,
                        'url' => 'https://pexels.com/200.jpg',
                        'photographer' => 'Kitty Photographer',
                        'photographer_url' => 'https://pexels.com/kitty-photographer',
                        'src' => [
                            'original' => 'https://pexels.com/200-orig.jpg',
                            'medium' => 'https://pexels.com/200-med.jpg',
                            'small' => 'https://pexels.com/200-small.jpg',
                        ]
                    ]
                ],
                'total_results' => 1
            ], 200),
        ]);

        $service = new ContentCalendarService();
        $summary = $service->generateScheduleForDays(1, [
            'posts_per_day' => 1,
            'media_type' => 'photo' // only photo so we only hit searchPhotos API
        ]);

        $this->assertEquals(1, $summary['created']);
        $this->assertEquals(0, $summary['failed']);

        // Assert media item was saved in DB
        $this->assertDatabaseHas('media_items', [
            'pexels_id' => '200',
            'photographer' => 'Kitty Photographer',
        ]);
    }
}
