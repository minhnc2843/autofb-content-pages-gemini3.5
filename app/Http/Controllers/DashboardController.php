<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use App\Models\PageInsight;
use App\Models\AiAnalysis;
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

        // Group insights by fetched_date
        $insightsList = PageInsight::orderBy('fetched_date', 'asc')->get();
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
                $groupedInsights[$date]['impressions'] = $insight->values_json['value'] ?? 0;
            } elseif ($insight->metric === 'page_post_engagements') {
                $groupedInsights[$date]['engagements'] = $insight->values_json['value'] ?? 0;
            } elseif ($insight->metric === 'page_fans') {
                $groupedInsights[$date]['followers'] = $insight->values_json['value'] ?? 0;
            }
        }
        $insightsData = array_values($groupedInsights);

        // Fetch latest AI page audit
        $latestAudit = AiAnalysis::where('target_type', 'page_insight')
            ->where('target_id', 0)
            ->where('provider', 'gemini')
            ->orderBy('created_at', 'desc')
            ->first();

        $auditData = $latestAudit ? [
            'score' => $latestAudit->score,
            'strengths' => $latestAudit->result_json['strengths'] ?? [],
            'weaknesses' => $latestAudit->result_json['weaknesses'] ?? [],
            'suggestions' => $latestAudit->result_json['suggestions'] ?? [],
            'created_at' => $latestAudit->created_at->format('Y-m-d H:i'),
        ] : null;

        // Custom Phase 3.5 Statistics
        $today = \Carbon\Carbon::today()->format('Y-m-d');
        $scheduledToday = PostQueue::whereDate('scheduled_at', $today)->count();

        $next7DaysStart = \Carbon\Carbon::tomorrow();
        $next7DaysEnd = \Carbon\Carbon::tomorrow()->addDays(6)->endOfDay();
        $countsByDate = PostQueue::whereBetween('scheduled_at', [$next7DaysStart, $next7DaysEnd])
            ->get()
            ->groupBy(fn($post) => $post->scheduled_at->format('Y-m-d'))
            ->map(fn($group) => $group->count());

        $missingSlots7Days = 0;
        $totalScheduledNext7Days = 0;
        for ($i = 0; $i < 7; $i++) {
            $dateStr = \Carbon\Carbon::tomorrow()->addDays($i)->format('Y-m-d');
            $count = $countsByDate->get($dateStr, 0);
            $totalScheduledNext7Days += $count;
            if ($count < 3) {
                $missingSlots7Days++;
            }
        }
        $coveragePercent = min(100, (int)(($totalScheduledNext7Days / 21) * 100));

        $nextScheduledPosts = PostQueue::with(['topic', 'mediaItem'])
            ->where('status', 'approved')
            ->where('scheduled_at', '>', \Carbon\Carbon::now())
            ->orderBy('scheduled_at', 'asc')
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

        $failedPosts = PostQueue::with(['topic', 'mediaItem'])
            ->where('status', 'failed')
            ->orderBy('updated_at', 'desc')
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
            ]
        ]);
    }
}
