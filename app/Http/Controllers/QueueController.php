<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Services\FacebookPageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $service = new FacebookPageService();
        $publishMode = $service->getPublishMode();

        $query = PostQueue::with(['topic', 'mediaItem', 'aiAnalyses']);

        // Filter: status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter: topic_id
        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->input('topic_id'));
        }

        // Filter: search (caption)
        if ($request->filled('search')) {
            $query->where('caption', 'like', '%' . $request->input('search') . '%');
        }

        // Filter: date_from
        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->input('date_from'));
        }

        // Filter: date_to
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->input('date_to'));
        }

        // Filter: media_type
        if ($request->filled('media_type')) {
            $mediaType = $request->input('media_type');
            if ($mediaType === 'text') {
                $query->whereNull('media_item_id');
            } else {
                $query->whereHas('mediaItem', function ($q) use ($mediaType) {
                    $q->where('type', $mediaType);
                });
            }
        }

        // Sorting
        $sort = $request->input('sort', 'created_at_desc');
        switch ($sort) {
            case 'scheduled_at_asc':
                $query->orderBy('scheduled_at', 'asc');
                break;
            case 'scheduled_at_desc':
                $query->orderBy('scheduled_at', 'desc');
                break;
            case 'created_at_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'created_at_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $posts = $query->get()->map(function ($post) {
            // Get the latest Gemini analysis
            $analysis = $post->aiAnalyses
                ->where('provider', 'gemini')
                ->sortByDesc('created_at')
                ->first();

            return [
                'id' => $post->id,
                'caption' => $post->caption,
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at?->format('Y-m-d\TH:i'),
                'scheduled_at_display' => $post->scheduled_at?->format('Y-m-d H:i'),
                'topic_name' => $post->topic?->name ?? 'N/A',
                'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                'media_url' => $post->mediaItem?->url,
                'media_type' => $post->mediaItem?->type,
                'facebook_post_id' => $post->facebook_post_id,
                'error_message' => $post->error_message,
                'ai_analysis' => $analysis ? [
                    'score' => $analysis->score,
                    'strengths' => $analysis->result_json['strengths'] ?? [],
                    'weaknesses' => $analysis->result_json['weaknesses'] ?? [],
                    'suggestions' => $analysis->result_json['suggestions'] ?? [],
                ] : null,
            ];
        });

        $topics = Topic::all()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
            ];
        });

        return Inertia::render('Queue/Index', [
            'posts' => $posts,
            'publishMode' => $publishMode,
            'filters' => $request->only(['status', 'media_type', 'topic_id', 'date_from', 'date_to', 'search', 'sort']),
            'topics' => $topics,
        ]);
    }

    public function edit(PostQueue $post)
    {
        $post->load(['topic', 'mediaItem', 'statusHistories' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        return Inertia::render('Queue/Edit', [
            'post' => [
                'id' => $post->id,
                'caption' => $post->caption,
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at?->format('Y-m-d\TH:i'),
                'topic_name' => $post->topic?->name ?? 'N/A',
                'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                'media_url' => $post->mediaItem?->url,
                'media_type' => $post->mediaItem?->type,
                'facebook_post_id' => $post->facebook_post_id,
                'error_message' => $post->error_message,
                'status_history' => $post->statusHistories->map(function ($h) {
                    return [
                        'from_status' => $h->from_status,
                        'to_status' => $h->to_status,
                        'changed_by' => $h->changed_by,
                        'created_at' => $h->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
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

    /**
     * Analyze and score a post using Gemini AI.
     */
    public function analyze(PostQueue $post)
    {
        $gemini = new \App\Services\AI\GeminiService();

        // Check if API Key is set
        if (empty($gemini->getApiKey())) {
            return redirect()->route('queue.index')
                ->with('error', 'Gemini API key is not configured. Please set GEMINI_API_KEY in Settings.');
        }

        // Cache checking: Check if post has not been modified since last analysis
        $cache = \App\Models\AiAnalysis::where('target_type', 'post_queue')
            ->where('target_id', $post->id)
            ->where('provider', 'gemini')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($cache && $cache->created_at >= $post->updated_at) {
            return redirect()->route('queue.index')
                ->with('success', 'Loaded post analysis from cache (score: ' . $cache->score . ').');
        }

        $post->load('mediaItem');
        $postData = [
            'caption' => $post->caption,
            'media' => $post->mediaItem ? [
                'type' => $post->mediaItem->type,
                'photographer' => $post->mediaItem->photographer,
                'width' => $post->mediaItem->width,
                'height' => $post->mediaItem->height,
            ] : null,
        ];

        try {
            $result = $gemini->scorePost($postData);

            if (isset($result['error'])) {
                return redirect()->route('queue.index')
                    ->with('error', 'AI Analysis failed: ' . $result['error']);
            }

            // Save to database
            \App\Models\AiAnalysis::updateOrCreate(
                [
                    'target_type' => 'post_queue',
                    'target_id' => $post->id,
                    'provider' => 'gemini',
                ],
                [
                    'score' => $result['score'] ?? 50,
                    'result_json' => $result,
                    'raw_response' => json_encode($result['raw_response'] ?? []),
                ]
            );

            return redirect()->route('queue.index')
                ->with('success', 'Post scored by AI successfully! (Score: ' . ($result['score'] ?? 50) . ')');

        } catch (\Exception $e) {
            return redirect()->route('queue.index')
                ->with('error', 'AI Analysis error: ' . $e->getMessage());
        }
    }

    /**
     * Handle batch actions on multiple queue posts.
     */
    public function batchAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:posts_queue,id',
            'action' => 'required|string|in:approve,unapprove,delete,reschedule,retry,draft',
            'scheduled_at' => 'nullable|date',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];
        $scheduledAt = $validated['scheduled_at'] ?? null;

        $posts = PostQueue::whereIn('id', $ids)->get();
        $count = 0;

        switch ($action) {
            case 'approve':
                foreach ($posts as $post) {
                    if ($post->status === 'draft') {
                        $post->update(['status' => 'approved']);
                        $count++;
                    }
                }
                $message = "Approved {$count} draft posts.";
                break;

            case 'unapprove':
                foreach ($posts as $post) {
                    if ($post->status === 'approved') {
                        $post->update(['status' => 'draft']);
                        $count++;
                    }
                }
                $message = "Moved {$count} approved posts back to draft.";
                break;

            case 'delete':
                foreach ($posts as $post) {
                    if (in_array($post->status, ['draft', 'failed'])) {
                        $post->delete();
                        $count++;
                    }
                }
                $message = "Deleted {$count} draft/failed posts.";
                break;

            case 'reschedule':
                if (!$scheduledAt) {
                    return redirect()->back()->with('error', 'Reschedule date and time is required.');
                }
                foreach ($posts as $post) {
                    if (!in_array($post->status, ['published', 'published_fake'])) {
                        $post->update(['scheduled_at' => $scheduledAt]);
                        $count++;
                    }
                }
                $message = "Rescheduled {$count} posts to " . date('Y-m-d H:i', strtotime($scheduledAt)) . ".";
                break;

            case 'retry':
                foreach ($posts as $post) {
                    if ($post->status === 'failed') {
                        $post->update([
                            'status' => 'approved',
                            'publish_attempts' => 0,
                            'error_message' => null,
                        ]);
                        $count++;
                    }
                }
                $message = "Prepared {$count} failed posts for retry.";
                break;

            case 'draft':
                foreach ($posts as $post) {
                    if (in_array($post->status, ['approved', 'failed'])) {
                        $post->update(['status' => 'draft']);
                        $count++;
                    }
                }
                $message = "Moved {$count} posts back to draft.";
                break;
        }

        return redirect()->route('queue.index')->with('success', $message);
    }
}
