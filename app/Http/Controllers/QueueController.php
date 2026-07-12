<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use App\Services\FacebookPageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueController extends Controller
{
    public function index()
    {
        $service = new FacebookPageService();
        $publishMode = $service->getPublishMode();

        $posts = PostQueue::with(['topic', 'mediaItem'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'caption' => $post->caption,
                    'status' => $post->status,
                    'scheduled_at' => $post->scheduled_at?->format('Y-m-d\TH:i'),
                    'scheduled_at_display' => $post->scheduled_at?->format('Y-m-d H:i'),
                    'topic_name' => $post->topic?->name ?? 'N/A',
                    'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                    'media_type' => $post->mediaItem?->type,
                    'facebook_post_id' => $post->facebook_post_id,
                    'error_message' => $post->error_message,
                ];
            });

        return Inertia::render('Queue/Index', [
            'posts' => $posts,
            'publishMode' => $publishMode,
        ]);
    }

    public function edit(PostQueue $post)
    {
        $post->load(['topic', 'mediaItem']);

        return Inertia::render('Queue/Edit', [
            'post' => [
                'id' => $post->id,
                'caption' => $post->caption,
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at?->format('Y-m-d\TH:i'),
                'topic_name' => $post->topic?->name ?? 'N/A',
                'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                'media_type' => $post->mediaItem?->type,
                'facebook_post_id' => $post->facebook_post_id,
                'error_message' => $post->error_message,
            ],
        ]);
    }

    public function update(Request $request, PostQueue $post)
    {
        $validated = $request->validate([
            'caption' => 'required|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $post->update($validated);

        return redirect()->route('queue.index')
            ->with('success', 'Post updated successfully.');
    }

    public function approve(PostQueue $post)
    {
        if ($post->status !== 'draft') {
            return redirect()->route('queue.index')
                ->with('error', 'Only draft posts can be approved.');
        }

        $post->update(['status' => 'approved']);

        return redirect()->route('queue.index')
            ->with('success', 'Post approved successfully.');
    }

    public function unapprove(PostQueue $post)
    {
        if ($post->status !== 'approved') {
            return redirect()->route('queue.index')
                ->with('error', 'Only approved posts can be unapproved.');
        }

        $post->update(['status' => 'draft']);

        return redirect()->route('queue.index')
            ->with('success', 'Post unapproved, moved back to draft.');
    }

    public function destroy(PostQueue $post)
    {
        if (in_array($post->status, ['published_fake', 'published'])) {
            return redirect()->route('queue.index')
                ->with('error', 'Published posts cannot be deleted.');
        }

        $post->delete();

        return redirect()->route('queue.index')
            ->with('success', 'Post deleted successfully.');
    }

    /**
     * Publish a single approved post immediately.
     */
    public function publishNow(PostQueue $post)
    {
        if ($post->status !== 'approved') {
            return redirect()->route('queue.index')
                ->with('error', 'Only approved posts can be published.');
        }

        $service = new FacebookPageService();

        try {
            $result = $service->publishPost($post);

            if ($result['success']) {
                $modeLabel = ($result['mode'] ?? $service->getPublishMode()) === 'fake'
                    ? '(Fake mode — no real Facebook API call)'
                    : '(Published to Facebook)';
                return redirect()->route('queue.index')
                    ->with('success', "Post published successfully! {$modeLabel}");
            } else {
                return redirect()->route('queue.index')
                    ->with('error', "Publish failed: {$result['message']}");
            }
        } catch (\Exception $e) {
            return redirect()->route('queue.index')
                ->with('error', "Publish error: {$e->getMessage()}");
        }
    }
}
