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

        $query = PostQueue::with(['page', 'topic', 'mediaItem', 'aiAnalyses']);

        // Filter: page_id
        if ($request->filled('page_id')) {
            $query->where('page_id', $request->input('page_id'));
        }

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

        $perPage = min(100, intval($request->input('per_page', 20)));
        if ($perPage < 1) {
            $perPage = 20;
        }

        $posts = $query->paginate($perPage)->through(function ($post) {
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
                'page_name' => $post->page?->name ?? 'Default Page',
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

        $posts->withQueryString();

        $topics = Topic::all()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
            ];
        });

        $lastRunAtStr = \App\Models\Setting::getValue('PUBLISH_DUE_LAST_RUN_AT');
        $lastFound = \App\Models\Setting::getValue('PUBLISH_DUE_LAST_FOUND', '0');
        $lastPublished = \App\Models\Setting::getValue('PUBLISH_DUE_LAST_PUBLISHED', '0');
        $lastFailed = \App\Models\Setting::getValue('PUBLISH_DUE_LAST_FAILED', '0');

        $isRecent = false;
        if (!empty($lastRunAtStr)) {
            $lastRunAt = \Carbon\Carbon::parse($lastRunAtStr);
            $isRecent = $lastRunAt->diffInMinutes(now()) <= 2;
        }

        $schedulerStatus = [
            'last_run_at' => $lastRunAtStr,
            'last_found' => intval($lastFound),
            'last_published' => intval($lastPublished),
            'last_failed' => intval($lastFailed),
            'is_recent' => $isRecent,
        ];

        $pages = \App\Models\Page::where('is_active', true)->get();

        return Inertia::render('Queue/Index', [
            'posts' => $posts,
            'publishMode' => $publishMode,
            'filters' => $request->only(['status', 'media_type', 'topic_id', 'date_from', 'date_to', 'search', 'sort', 'page_id']),
            'topics' => $topics,
            'schedulerStatus' => $schedulerStatus,
            'pages' => $pages,
        ]);
    }

    public function edit(PostQueue $post)
    {
        $post->load(['topic', 'mediaItem', 'statusHistories' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        $service = new FacebookPageService();
        $publishMode = $service->getPublishMode();

        $publishLogs = $post->publishLogs()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'status' => $log->status,
                    'mode' => $log->mode,
                    'error_message' => $log->error_message,
                    'request_summary' => $log->request_summary,
                    'response_json' => $log->response_json,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Queue/Edit', [
            'publishMode' => $publishMode,
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
                'publish_attempts' => $post->publish_attempts,
                'publish_started_at' => $post->publish_started_at?->format('Y-m-d H:i:s'),
                'published_at' => $post->published_at?->format('Y-m-d H:i:s'),
                'publish_logs' => $publishLogs,
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
            'approve_after_save' => 'nullable|boolean',
        ]);

        $scheduledAt = null;
        if (!empty($validated['scheduled_at'])) {
            $scheduledAt = \Carbon\Carbon::parse(
                $validated['scheduled_at'],
                config('app.timezone')
            );
        }

        $post->update([
            'caption' => $validated['caption'],
            'scheduled_at' => $scheduledAt,
        ]);

        $approveAfterSave = $request->boolean('approve_after_save');

        if ($approveAfterSave) {
            if (in_array($post->status, ['draft', 'failed'])) {
                $post->status_change_reason = 'save_and_approve';
                $post->update([
                    'status' => 'approved',
                    'error_message' => null,
                ]);
            }
        }

        return redirect()
            ->route('queue.edit', $post)
            ->with('success', $approveAfterSave
                ? 'Post saved and approved successfully.'
                : 'Post saved successfully.'
            );
    }

    public function approve(Request $request, PostQueue $post)
    {
        if (!in_array($post->status, ['draft', 'failed'])) {
            return redirect()->route('queue.index')
                ->with('error', 'Only draft or failed posts can be approved.');
        }

        $post->status_change_reason = 'manual_approve';
        $post->update([
            'status' => 'approved',
            'error_message' => null,
        ]);

        if (str_contains($request->headers->get('referer', ''), '/edit')) {
            return redirect()->route('queue.edit', $post)
                ->with('success', 'Post approved successfully.');
        }

        return redirect()->route('queue.index')
            ->with('success', 'Post approved successfully.');
    }

    public function unapprove(Request $request, PostQueue $post)
    {
        if ($post->status !== 'approved') {
            return redirect()->route('queue.index')
                ->with('error', 'Only approved posts can be unapproved.');
        }

        $post->status_change_reason = 'manual_unapprove';
        $post->update(['status' => 'draft']);

        if (str_contains($request->headers->get('referer', ''), '/edit')) {
            return redirect()->route('queue.edit', $post)
                ->with('success', 'Post unapproved, moved back to draft.');
        }

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
    public function publishNow(Request $request, PostQueue $post)
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

                if (str_contains($request->headers->get('referer', ''), '/edit')) {
                    return redirect()->route('queue.edit', $post)
                        ->with('success', "Post published successfully! {$modeLabel}");
                }

                return redirect()->route('queue.index')
                    ->with('success', "Post published successfully! {$modeLabel}");
            } else {
                if (str_contains($request->headers->get('referer', ''), '/edit')) {
                    return redirect()->route('queue.edit', $post)
                        ->with('error', "Publish failed: {$result['message']}");
                }

                return redirect()->route('queue.index')
                    ->with('error', "Publish failed: {$result['message']}");
            }
        } catch (\Exception $e) {
            if (str_contains($request->headers->get('referer', ''), '/edit')) {
                return redirect()->route('queue.edit', $post)
                    ->with('error', "Publish error: {$e->getMessage()}");
            }

            return redirect()->route('queue.index')
                ->with('error', "Publish error: {$e->getMessage()}");
        }
    }

    public function analyze(PostQueue $post)
    {
        $gemini = new \App\Services\AI\GeminiService();

        if (!$gemini->isEnabled()) {
            return redirect()->route('queue.index')
                ->with('error', 'Gemini AI features are currently disabled. Please enable it in Settings.');
        }

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
        $scheduledAt = null;
        if (!empty($validated['scheduled_at'])) {
            $scheduledAt = \Carbon\Carbon::parse(
                $validated['scheduled_at'],
                config('app.timezone')
            );
        }

        $posts = PostQueue::whereIn('id', $ids)->get();
        $count = 0;
        $skipped = 0;

        switch ($action) {
            case 'approve':
                foreach ($posts as $post) {
                    if (in_array($post->status, ['draft', 'failed'])) {
                        $post->status_change_reason = 'batch_approve';
                        $post->update([
                            'status' => 'approved',
                            'error_message' => null,
                        ]);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
                $message = "Approved {$count} posts. (Skipped {$skipped} non-draft/failed posts)";
                break;

            case 'unapprove':
                foreach ($posts as $post) {
                    if ($post->status === 'approved') {
                        $post->status_change_reason = 'batch_unapprove';
                        $post->update(['status' => 'draft']);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
                $message = "Moved {$count} approved posts back to draft. (Skipped {$skipped} posts)";
                break;

            case 'delete':
                foreach ($posts as $post) {
                    if ($post->status === 'draft') {
                        $post->delete();
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
                $message = "Deleted {$count} draft posts. (Skipped {$skipped} non-draft posts)";
                break;

            case 'reschedule':
                if (!$scheduledAt) {
                    return redirect()->back()->with('error', 'Reschedule date and time is required.');
                }
                foreach ($posts as $post) {
                    if (!in_array($post->status, ['published', 'published_fake'])) {
                        $post->update(['scheduled_at' => $scheduledAt]);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
                $message = "Rescheduled {$count} posts. (Skipped {$skipped} published posts)";
                break;

            case 'retry':
                foreach ($posts as $post) {
                    if ($post->status === 'failed') {
                        $post->status_change_reason = 'batch_retry';
                        $post->update([
                            'status' => 'approved',
                            'publish_attempts' => 0,
                            'error_message' => null,
                        ]);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
                $message = "Prepared {$count} failed posts for retry. (Skipped {$skipped} non-failed posts)";
                break;

            case 'draft':
                foreach ($posts as $post) {
                    if (in_array($post->status, ['approved', 'failed'])) {
                        $post->status_change_reason = 'batch_draft';
                        $post->update(['status' => 'draft']);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
                $message = "Moved {$count} posts back to draft. (Skipped {$skipped} posts)";
                break;
        }

        return redirect()->route('queue.index')->with('success', $message);
    }

    public function publishDueNow(Request $request)
    {
        $service = new \App\Services\DuePostPublisherService();
        $result = $service->publishDuePosts(false);

        $found = $result['found'];
        $published = $result['published'];
        $failed = $result['failed'];

        \App\Models\Setting::setValue('PUBLISH_DUE_LAST_RUN_AT', now()->toDateTimeString());
        \App\Models\Setting::setValue('PUBLISH_DUE_LAST_FOUND', (string) $found);
        \App\Models\Setting::setValue('PUBLISH_DUE_LAST_PUBLISHED', (string) $published);
        \App\Models\Setting::setValue('PUBLISH_DUE_LAST_FAILED', (string) $failed);

        return redirect()->back()->with('success', "Due publish completed: found {$found}, published {$published}, failed {$failed}.");
    }
}
