<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Services\DuplicateProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateProtectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_caption_normalization_collapses_whitespace_and_lowercase(): void
    {
        $dup = new DuplicateProtectionService();

        $this->assertEquals('hello world test', $dup->normalizeCaption("  Hello   WORLD\n  test  "));
    }

    public function test_is_caption_duplicate_handles_normalized_captions(): void
    {
        $topic = Topic::create(['name' => 'Pets', 'keyword' => 'cat']);

        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Loving my new cat! #pets',
            'status' => 'draft',
            'scheduled_at' => now()->addHour(),
        ]);

        $dup = new DuplicateProtectionService();

        // Exact match
        $this->assertTrue($dup->isCaptionDuplicate('Loving my new cat! #pets'));

        // Case/whitespace variant match
        $this->assertTrue($dup->isCaptionDuplicate("  LOVING my\n  NEW cat!   #pets  "));

        // Different caption
        $this->assertFalse($dup->isCaptionDuplicate('Loving my dog!'));
    }

    public function test_is_slot_taken_correctly_finds_minute_range_overlaps(): void
    {
        $topic = Topic::create(['name' => 'Pets', 'keyword' => 'cat']);
        $time = now()->addDay()->setTime(13, 0, 0); // 13:00:00

        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Post A',
            'status' => 'draft',
            'scheduled_at' => $time,
        ]);

        $dup = new DuplicateProtectionService();

        // Exact match
        $this->assertTrue($dup->isSlotTaken($time));

        // Within same minute (different seconds)
        $this->assertTrue($dup->isSlotTaken($time->copy()->addSeconds(30)));

        // Different hour
        $this->assertFalse($dup->isSlotTaken($time->copy()->addHour()));
    }

    public function test_day_has_enough_posts_limit_checks(): void
    {
        $topic = Topic::create(['name' => 'Pets', 'keyword' => 'cat']);
        $date = now()->addDay();

        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Post A',
            'status' => 'draft',
            'scheduled_at' => $date->copy()->setTime(8, 0),
        ]);

        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Post B',
            'status' => 'draft',
            'scheduled_at' => $date->copy()->setTime(13, 0),
        ]);

        $dup = new DuplicateProtectionService();

        $this->assertFalse($dup->dayHasEnoughPosts($date, 3));
        
        // Add a third post
        PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Post C',
            'status' => 'draft',
            'scheduled_at' => $date->copy()->setTime(20, 0),
        ]);

        $this->assertTrue($dup->dayHasEnoughPosts($date, 3));
    }
}
