<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use App\Models\PageInsight;
use App\Models\AiAnalysis;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $pageId = $request->input('page_id');
        $resolvedPage = $pageId ? Page::with('profile')->find($pageId) : null;

        // Base queries
        $draftQ = PostQueue::draft();
        $approvedQ = PostQueue::approved();
        $publishedQ = PostQueue::published();
        $publishedFakeQ = PostQueue::publishedFake();
        $failedQ = PostQueue::failed();

        if ($pageId) {
            $draftQ->where('page_id', $pageId);
            $approvedQ->where('page_id', $pageId);
            $publishedQ->where('page_id', $pageId);
            $publishedFakeQ->where('page_id', $pageId);
            $failedQ->where('page_id', $pageId);
        }

        $stats = [
            'draft' => $draftQ->count(),
            'approved' => $approvedQ->count(),
            'published' => $publishedQ->count(),
            'published_fake' => $publishedFakeQ->count(),
            'failed' => $failedQ->count(),
        ];

        $recentPostsQuery = PostQueue::with(['topic', 'mediaItem'])
            ->whereNotNull('scheduled_at');
        if ($pageId) {
            $recentPostsQuery->where('page_id', $pageId);
        }
        $recentPosts = $recentPostsQuery->orderBy('scheduled_at', 'desc')
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

        // Insights query
        $insightsQuery = PageInsight::query();
        if ($pageId) {
            $insightsQuery->where('page_id', $pageId);
        }
        $insightsList = $insightsQuery->orderBy('fetched_date', 'asc')->get();
        $groupedInsights = [];
        foreach ($insightsList as $insight) {
            $date = $insight->fetched_date->format('Y-m-d');
            if (!isset($groupedInsights[$date])) {
                $groupedInsights[$date] = [
                    'date' => $date,
                    'impressions' => 0,
                    'engagements' => 0,
                    'followers' => 0,
                ];
            }
            if ($insight->metric === 'page_impressions') {
                $groupedInsights[$date]['impressions'] += $insight->values_json['value'] ?? 0;
            } elseif ($insight->metric === 'page_post_engagements') {
                $groupedInsights[$date]['engagements'] += $insight->values_json['value'] ?? 0;
            } elseif ($insight->metric === 'page_fans') {
                $groupedInsights[$date]['followers'] += $insight->values_json['value'] ?? 0;
            }
        }
        $insightsData = array_values($groupedInsights);

        // Fetch latest AI page audit
        $auditQuery = AiAnalysis::where('target_type', 'page_insight');
        if ($pageId) {
            $auditQuery->where('page_id', $pageId);
        } else {
            $auditQuery->where('target_id', 0);
        }
        $latestAudit = $auditQuery->orderBy('created_at', 'desc')->first();

        $auditData = $latestAudit ? [
            'score' => $latestAudit->score,
            'strengths' => $latestAudit->result_json['strengths'] ?? [],
            'weaknesses' => $latestAudit->result_json['weaknesses'] ?? [],
            'suggestions' => $latestAudit->result_json['suggestions'] ?? [],
            'created_at' => $latestAudit->created_at->format('Y-m-d H:i'),
        ] : null;

        // Custom Statistics
        $today = \Carbon\Carbon::today()->format('Y-m-d');
        $scheduledTodayQuery = PostQueue::whereDate('scheduled_at', $today);
        if ($pageId) {
            $scheduledTodayQuery->where('page_id', $pageId);
        }
        $scheduledToday = $scheduledTodayQuery->count();

        $next7DaysStart = \Carbon\Carbon::tomorrow();
        $next7DaysEnd = \Carbon\Carbon::tomorrow()->addDays(6)->endOfDay();

        $countsQuery = PostQueue::whereBetween('scheduled_at', [$next7DaysStart, $next7DaysEnd]);
        if ($pageId) {
            $countsQuery->where('page_id', $pageId);
        }
        $countsByDate = $countsQuery->get()
            ->groupBy(fn($post) => $post->scheduled_at->format('Y-m-d'))
            ->map(fn($group) => $group->count());

        $postsPerDay = $resolvedPage->profile->max_posts_per_day ?? 3;

        $missingSlots7Days = 0;
        $totalScheduledNext7Days = 0;
        for ($i = 0; $i < 7; $i++) {
            $dateStr = \Carbon\Carbon::tomorrow()->addDays($i)->format('Y-m-d');
            $count = $countsByDate->get($dateStr, 0);
            $totalScheduledNext7Days += $count;
            if ($count < $postsPerDay) {
                $missingSlots7Days++;
            }
        }
        $expectedTotal = $postsPerDay * 7;
        $coveragePercent = $expectedTotal > 0 ? min(100, (int)(($totalScheduledNext7Days / $expectedTotal) * 100)) : 100;

        $nextScheduledPostsQuery = PostQueue::with(['topic', 'mediaItem'])
            ->where('status', 'approved')
            ->where('scheduled_at', '>', \Carbon\Carbon::now());
        if ($pageId) {
            $nextScheduledPostsQuery->where('page_id', $pageId);
        }
        $nextScheduledPosts = $nextScheduledPostsQuery->orderBy('scheduled_at', 'asc')
            ->limit(3)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'caption' => $post->caption,
                    'scheduled_at' => $post->scheduled_at?->format('Y-m-d H:i'),
                    'topic_name' => $post->topic?->name ?? 'N/A',
                    'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                ];
            });

        $failedPostsQuery = PostQueue::with(['topic', 'mediaItem'])
            ->where('status', 'failed');
        if ($pageId) {
            $failedPostsQuery->where('page_id', $pageId);
        }
        $failedPosts = $failedPostsQuery->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'caption' => $post->caption,
                    'error_message' => $post->error_message,
                    'topic_name' => $post->topic?->name ?? 'N/A',
                    'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                ];
            });

        $service = new \App\Services\FacebookPageService($resolvedPage);
        $publishMode = $service->getPublishMode();

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

        $pages = Page::where('is_active', true)->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentPosts' => $recentPosts,
            'insights' => $insightsData,
            'audit' => $auditData,
            'extraStats' => [
                'scheduled_today' => $scheduledToday,
                'missing_slots_7_days' => $missingSlots7Days,
                'coverage_percent' => $coveragePercent,
                'next_scheduled_posts' => $nextScheduledPosts,
                'failed_posts' => $failedPosts,
            ],
            'publishMode' => $publishMode,
            'schedulerStatus' => $schedulerStatus,
            'pages' => $pages,
            'filters' => [
                'page_id' => $pageId,
            ],
        ]);
    }
}
