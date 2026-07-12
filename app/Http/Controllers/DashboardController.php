<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'draft' => PostQueue::draft()->count(),
            'approved' => PostQueue::approved()->count(),
            'published' => PostQueue::published()->count(),
            'published_fake' => PostQueue::publishedFake()->count(),
            'failed' => PostQueue::failed()->count(),
        ];

        $recentPosts = PostQueue::with(['topic', 'mediaItem'])
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'caption' => $post->caption,
                    'status' => $post->status,
                    'scheduled_at' => $post->scheduled_at?->format('Y-m-d H:i'),
                    'topic_name' => $post->topic?->name ?? 'N/A',
                    'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                    'media_type' => $post->mediaItem?->type,
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentPosts' => $recentPosts,
        ]);
    }
}
