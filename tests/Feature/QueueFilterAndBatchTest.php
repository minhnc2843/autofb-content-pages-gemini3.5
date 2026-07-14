<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Models\MediaItem;
use App\Models\PostStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueFilterAndBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_filters_work(): void
    {
        $topic1 = Topic::create(['name' => 'Topic A', 'keyword' => 'apple']);
        $topic2 = Topic::create(['name' => 'Topic B', 'keyword' => 'orange']);

        $media = MediaItem::create([
            'pexels_id' => '1',
            'type' => 'video',
            'url' => 'https://pexels.com/v.mp4',
            'thumbnail_url' => 'https://pexels.com/t.jpg',
            'photographer' => 'Photographer A'
        ]);

        // Draft text post under topic 1
        PostQueue::create([
            'caption' => 'Unique apple post',
            'status' => 'draft',
            'topic_id' => $topic1->id,
            'scheduled_at' => '2026-07-20 10:00:00'
        ]);

        // Approved video post under topic 2
        PostQueue::create([
            'caption' => 'Orange post',
            'status' => 'approved',
            'topic_id' => $topic2->id,
            'media_item_id' => $media->id,
            'scheduled_at' => '2026-07-25 15:00:00'
        ]);

        // Test status filter
        $this->get('/queue?status=draft')
            ->assertInertia(fn ($page) => $page
                ->component('Queue/Index')
                ->has('posts', 1)
                ->where('posts.0.caption', 'Unique apple post')
            );

        // Test topic filter
        $this->get('/queue?topic_id=' . $topic2->id)
            ->assertInertia(fn ($page) => $page
                ->component('Queue/Index')
                ->has('posts', 1)
                ->where('posts.0.caption', 'Orange post')
            );

        // Test search filter
        $this->get('/queue?search=apple')
            ->assertInertia(fn ($page) => $page
                ->component('Queue/Index')
                ->has('posts', 1)
                ->where('posts.0.caption', 'Unique apple post')
            );

        // Test media_type filter
        $this->get('/queue?media_type=video')
            ->assertInertia(fn ($page) => $page
                ->component('Queue/Index')
                ->has('posts', 1)
                ->where('posts.0.media_type', 'video')
            );

        // Test date range filter
        $this->get('/queue?date_from=2026-07-22')
            ->assertInertia(fn ($page) => $page
                ->component('Queue/Index')
                ->has('posts', 1)
                ->where('posts.0.caption', 'Orange post')
            );
    }

    public function test_status_change_triggers_history(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test caption',
            'status' => 'draft'
        ]);

        // Update status to approved
        $post->update(['status' => 'approved']);

        $this->assertDatabaseHas('post_status_histories', [
            'post_queue_id' => $post->id,
            'from_status' => 'draft',
            'to_status' => 'approved'
        ]);
    }

    public function test_batch_approve_action(): void
    {
        $post1 = PostQueue::create(['caption' => 'Post 1', 'status' => 'draft']);
        $post2 = PostQueue::create(['caption' => 'Post 2', 'status' => 'draft']);
        $post3 = PostQueue::create(['caption' => 'Post 3', 'status' => 'published']); // should be safe

        $response = $this->post('/queue/batch', [
            'ids' => [$post1->id, $post2->id, $post3->id],
            'action' => 'approve'
        ]);

        $response->assertRedirect('/queue');
        $this->assertEquals('approved', $post1->fresh()->status);
        $this->assertEquals('approved', $post2->fresh()->status);
        $this->assertEquals('published', $post3->fresh()->status); // untouched

        // Check history logs
        $this->assertDatabaseHas('post_status_histories', [
            'post_queue_id' => $post1->id,
            'from_status' => 'draft',
            'to_status' => 'approved'
        ]);
    }

    public function test_batch_delete_safely(): void
    {
        $post1 = PostQueue::create(['caption' => 'Post 1', 'status' => 'draft']);
        $post2 = PostQueue::create(['caption' => 'Post 2', 'status' => 'published']);

        $response = $this->post('/queue/batch', [
            'ids' => [$post1->id, $post2->id],
            'action' => 'delete'
        ]);

        $response->assertRedirect('/queue');
        $this->assertModelMissing($post1);
        $this->assertModelExists($post2); // published posts cannot be deleted
    }

    public function test_batch_reschedule(): void
    {
        $post1 = PostQueue::create(['caption' => 'Post 1', 'status' => 'draft']);
        $post2 = PostQueue::create(['caption' => 'Post 2', 'status' => 'published']);

        $newDate = '2026-08-01 12:00:00';

        $response = $this->post('/queue/batch', [
            'ids' => [$post1->id, $post2->id],
            'action' => 'reschedule',
            'scheduled_at' => $newDate
        ]);

        $response->assertRedirect('/queue');
        $this->assertEquals($newDate, $post1->fresh()->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertNull($post2->fresh()->scheduled_at); // published post date untouched
    }
}
