<?php

namespace Tests\Feature;

use App\Models\PostQueue;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueuePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_index_is_paginated(): void
    {
        $topic = Topic::create(['name' => 'Pets', 'keyword' => 'cat']);

        // Create 25 posts
        for ($i = 1; $i <= 25; $i++) {
            PostQueue::create([
                'topic_id' => $topic->id,
                'caption' => "Caption #{$i}",
                'status' => 'draft',
                'scheduled_at' => now()->addDays($i),
            ]);
        }

        $response = $this->get('/queue');

        $response->assertStatus(200);

        // Assert response has pagination keys
        $response->assertInertia(fn ($page) => $page
            ->component('Queue/Index')
            ->has('posts.data', 20) // Page 1 has 20 items (default)
            ->where('posts.total', 25)
            ->where('posts.current_page', 1)
        );
    }

    public function test_queue_filters_work_with_pagination(): void
    {
        $topic1 = Topic::create(['name' => 'Topic A', 'keyword' => 'a']);
        $topic2 = Topic::create(['name' => 'Topic B', 'keyword' => 'b']);

        // Create 5 posts for topic1
        for ($i = 1; $i <= 5; $i++) {
            PostQueue::create([
                'topic_id' => $topic1->id,
                'caption' => "Post A #{$i}",
                'status' => 'draft',
                'scheduled_at' => now()->addDays($i),
            ]);
        }

        // Create 2 posts for topic2
        for ($i = 1; $i <= 2; $i++) {
            PostQueue::create([
                'topic_id' => $topic2->id,
                'caption' => "Post B #{$i}",
                'status' => 'draft',
                'scheduled_at' => now()->addDays($i),
            ]);
        }

        // Query with topic filter
        $response = $this->get('/queue?topic_id=' . $topic2->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Queue/Index')
            ->has('posts.data', 2)
            ->where('posts.total', 2)
            ->where('filters.topic_id', (string)$topic2->id)
        );
    }
}
