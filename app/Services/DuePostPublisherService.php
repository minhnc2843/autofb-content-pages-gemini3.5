<?php

namespace App\Services;

use App\Models\PostQueue;
use App\Services\FacebookPageService;

class DuePostPublisherService
{
    protected FacebookPageService $fbService;

    public function __construct()
    {
        $this->fbService = new FacebookPageService();
    }

    public function publishDuePosts(bool $dryRun = false, ?int $pageId = null): array
    {
        $query = PostQueue::due()->with('mediaItem');

        if ($pageId) {
            $query->where('page_id', $pageId);
        }

        $duePosts = $query->get();
        $mode = $this->fbService->getPublishMode();

        $summary = [
            'found' => $duePosts->count(),
            'published' => 0,
            'failed' => 0,
            'mode' => $mode,
            'posts' => [],
        ];

        if ($dryRun) {
            foreach ($duePosts as $post) {
                $summary['posts'][] = [
                    'id' => $post->id,
                    'status_before' => $post->status,
                    'status_after' => $post->status,
                    'success' => true,
                    'message' => 'Dry run: post would be published.',
                    'scheduled_at' => $post->scheduled_at ? $post->scheduled_at->toDateTimeString() : null,
                    'media_type' => $post->mediaItem?->type ?? 'text',
                ];
            }
            return $summary;
        }

        foreach ($duePosts as $post) {
            $statusBefore = $post->status;
            try {
                $postPage = $post->page_id ? \App\Models\Page::find($post->page_id) : null;
                $postFbService = new FacebookPageService($postPage);
                $result = $postFbService->publishPost($post);

                if ($result['success']) {
                    $summary['published']++;
                    $summary['posts'][] = [
                        'id' => $post->id,
                        'status_before' => $statusBefore,
                        'status_after' => $post->refresh()->status,
                        'success' => true,
                        'message' => $result['message'],
                        'scheduled_at' => $post->scheduled_at ? $post->scheduled_at->toDateTimeString() : null,
                        'media_type' => $post->mediaItem?->type ?? 'text',
                    ];
                } else {
                    $summary['failed']++;
                    $summary['posts'][] = [
                        'id' => $post->id,
                        'status_before' => $statusBefore,
                        'status_after' => $post->refresh()->status,
                        'success' => false,
                        'message' => $result['message'],
                        'scheduled_at' => $post->scheduled_at ? $post->scheduled_at->toDateTimeString() : null,
                        'media_type' => $post->mediaItem?->type ?? 'text',
                    ];
                }
            } catch (\Exception $e) {
                $summary['failed']++;
                $post->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $summary['posts'][] = [
                    'id' => $post->id,
                    'status_before' => $statusBefore,
                    'status_after' => 'failed',
                    'success' => false,
                    'message' => $e->getMessage(),
                    'scheduled_at' => $post->scheduled_at ? $post->scheduled_at->toDateTimeString() : null,
                    'media_type' => $post->mediaItem?->type ?? 'text',
                ];
            }
        }

        return $summary;
    }
}
