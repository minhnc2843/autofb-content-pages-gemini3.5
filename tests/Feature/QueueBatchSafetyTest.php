<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Models\PostStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueBatchSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_delete_only_deletes_draft_posts(): void
    {
        $topic = Topic::create(['name' => 'General', 'keyword' => 'gen']);

        $draft = PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Draft post',
            'status' => 'draft',
            'scheduled_at' => now()->addHour(),
        ]);

        $failed = PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Failed post',
            'status' => 'failed',
            'scheduled_at' => now()->addHour(),
        ]);

        $approved = PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Approved post',
            'status' => 'approved',
            'scheduled_at' => now()->addHour(),
        ]);

        // Trigger batch delete
        $response = $this->post('/queue/batch', [
            'ids' => [$draft->id, $failed->id, $approved->id],
            'action' => 'delete',
        ]);

        $response->assertRedirect('/queue');

        // Verify only draft was deleted, others skipped
        $this->assertDatabaseMissing('posts_queue', ['id' => $draft->id]);
        $this->assertDatabaseHas('posts_queue', ['id' => $failed->id]);
        $this->assertDatabaseHas('posts_queue', ['id' => $approved->id]);
    }

    public function test_batch_actions_never_modify_published_posts(): void
    {
        $topic = Topic::create(['name' => 'General', 'keyword' => 'gen']);

        $published = PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Published post',
            'status' => 'published',
            'scheduled_at' => now()->subHour(),
        ]);

        // Try batch unapprove on a published post
        $response = $this->post('/queue/batch', [
            'ids' => [$published->id],
            'action' => 'unapprove',
        ]);

        $response->assertRedirect('/queue');

        // Confirm status remains published
        $this->assertDatabaseHas('posts_queue', [
            'id' => $published->id,
            'status' => 'published',
        ]);
    }

    public function test_batch_actions_record_change_reason_in_history(): void
    {
        $topic = Topic::create(['name' => 'General', 'keyword' => 'gen']);

        $draft = PostQueue::create([
            'topic_id' => $topic->id,
            'caption' => 'Draft post',
            'status' => 'draft',
            'scheduled_at' => now()->addHour(),
        ]);

        // Approve draft via batch action
        $response = $this->post('/queue/batch', [
            'ids' => [$draft->id],
            'action' => 'approve',
        ]);

        $response->assertRedirect('/queue');

        // Verify status history was logged with 'batch_approve' reason
        $this->assertDatabaseHas('post_status_histories', [
            'post_queue_id' => $draft->id,
            'from_status' => 'draft',
            'to_status' => 'approved',
            'reason' => 'batch_approve',
        ]);
    }
}
