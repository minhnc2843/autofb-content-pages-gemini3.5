<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_draft_post(): void
    {
        $mediaItem = MediaItem::create([
            'pexels_id' => '12345',
            'type' => 'photo',
            'url' => 'https://example.com/photo.jpg',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'photographer' => 'Test Photographer',
        ]);

        $post = PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => 'Test caption for draft post',
            'status' => 'draft',
            'scheduled_at' => now()->addHour(),
        ]);

        $this->assertDatabaseHas('posts_queue', [
            'id' => $post->id,
            'status' => 'draft',
            'caption' => 'Test caption for draft post',
        ]);
    }

    public function test_can_approve_post(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test caption',
            'status' => 'draft',
        ]);

        $response = $this->patch("/queue/{$post->id}/approve");

        $response->assertRedirect('/queue');
        $this->assertDatabaseHas('posts_queue', [
            'id' => $post->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_unapprove_post(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test caption',
            'status' => 'approved',
        ]);

        $response = $this->patch("/queue/{$post->id}/unapprove");

        $response->assertRedirect('/queue');
        $this->assertDatabaseHas('posts_queue', [
            'id' => $post->id,
            'status' => 'draft',
        ]);
    }

    public function test_can_delete_draft_post(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test caption',
            'status' => 'draft',
        ]);

        $response = $this->delete("/queue/{$post->id}");

        $response->assertRedirect('/queue');
        $this->assertDatabaseMissing('posts_queue', [
            'id' => $post->id,
        ]);
    }

    public function test_cannot_delete_published_post(): void
    {
        $post = PostQueue::create([
            'caption' => 'Test caption',
            'status' => 'published_fake',
        ]);

        $response = $this->delete("/queue/{$post->id}");

        $response->assertRedirect('/queue');
        $this->assertDatabaseHas('posts_queue', [
            'id' => $post->id,
        ]);
    }

    public function test_can_update_post_caption(): void
    {
        $post = PostQueue::create([
            'caption' => 'Old caption',
            'status' => 'draft',
        ]);

        $response = $this->put("/queue/{$post->id}", [
            'caption' => 'New updated caption',
            'scheduled_at' => '2025-12-31 10:00',
        ]);

        $response->assertRedirect(route('queue.edit', $post));
        $this->assertDatabaseHas('posts_queue', [
            'id' => $post->id,
            'caption' => 'New updated caption',
        ]);
    }
}
