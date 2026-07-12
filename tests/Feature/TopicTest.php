<?php

namespace Tests\Feature;

use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_topic(): void
    {
        $response = $this->post('/topics', [
            'name' => 'Nature Photography',
            'keyword' => 'nature',
            'language' => 'english',
            'media_type' => 'photo',
            'is_active' => true,
        ]);

        $response->assertRedirect('/topics');
        $this->assertDatabaseHas('topics', [
            'name' => 'Nature Photography',
            'keyword' => 'nature',
            'language' => 'english',
            'media_type' => 'photo',
            'is_active' => true,
        ]);
    }

    public function test_can_update_topic(): void
    {
        $topic = Topic::create([
            'name' => 'Old Name',
            'keyword' => 'old',
            'language' => 'english',
            'media_type' => 'photo',
            'is_active' => true,
        ]);

        $response = $this->put("/topics/{$topic->id}", [
            'name' => 'New Name',
            'keyword' => 'new',
            'language' => 'vietnamese',
            'media_type' => 'video',
            'is_active' => false,
        ]);

        $response->assertRedirect('/topics');
        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'name' => 'New Name',
            'keyword' => 'new',
            'language' => 'vietnamese',
            'media_type' => 'video',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_topic(): void
    {
        $topic = Topic::create([
            'name' => 'To Delete',
            'keyword' => 'delete',
            'language' => 'english',
            'media_type' => 'photo',
            'is_active' => true,
        ]);

        $response = $this->delete("/topics/{$topic->id}");

        $response->assertRedirect('/topics');
        $this->assertDatabaseMissing('topics', [
            'id' => $topic->id,
        ]);
    }

    public function test_can_toggle_topic_active(): void
    {
        $topic = Topic::create([
            'name' => 'Toggle Test',
            'keyword' => 'toggle',
            'language' => 'english',
            'media_type' => 'photo',
            'is_active' => true,
        ]);

        $this->patch("/topics/{$topic->id}/toggle");

        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'is_active' => false,
        ]);
    }

    public function test_create_topic_requires_name(): void
    {
        $response = $this->post('/topics', [
            'keyword' => 'nature',
            'language' => 'english',
            'media_type' => 'photo',
        ]);

        $response->assertSessionHasErrors(['name']);
    }
}
