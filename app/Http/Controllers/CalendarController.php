<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Models\Page;
use App\Services\ContentCalendarService;
use App\Services\AI\AIContentPlannerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $year = (int)$request->input('year', Carbon::now()->year);
        $month = (int)$request->input('month', Carbon::now()->month);
        $pageId = $request->input('page_id');

        // Validate and bound month/year
        if ($month < 1 || $month > 12) {
            $month = (int)Carbon::now()->month;
        }
        if ($year < 1970 || $year > 2099) {
            $year = (int)Carbon::now()->year;
        }

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $query = PostQueue::with(['topic', 'mediaItem'])
            ->whereBetween('scheduled_at', [$startDate, $endDate]);

        // Filter: page_id
        if ($request->filled('page_id') && $request->input('page_id') !== 'all') {
            $query->where('page_id', $pageId);
        }

        // Filter: status (all means no filter)
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter: topic_id (all/empty means no filter)
        if ($request->filled('topic_id') && $request->input('topic_id') !== 'all') {
            $query->where('topic_id', $request->input('topic_id'));
        }

        $posts = $query->get()->map(function ($post) {
            return [
                'id' => $post->id,
                'caption' => $post->caption,
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at->format('Y-m-d\TH:i:s'),
                'scheduled_date' => $post->scheduled_at->format('Y-m-d'),
                'scheduled_time' => $post->scheduled_at->format('H:i'),
                'topic_name' => $post->topic?->name ?? 'N/A',
                'thumbnail_url' => $post->mediaItem?->thumbnail_url,
                'media_type' => $post->mediaItem?->type,
            ];
        });

        // Calculate missing slots (dates in this month where posts < max_posts_per_day)
        $resolvedPage = null;
        if ($request->filled('page_id') && $request->input('page_id') !== 'all') {
            $resolvedPage = Page::with('profile')->find($pageId);
        }
        $postsPerDay = $resolvedPage->profile->max_posts_per_day ?? 3;

        // Group by scheduled date
        $countsQuery = PostQueue::whereBetween('scheduled_at', [$startDate, $endDate]);
        if ($request->filled('page_id') && $request->input('page_id') !== 'all') {
            $countsQuery->where('page_id', $pageId);
        }
        
        $countsByDate = $countsQuery->get()
            ->groupBy(function ($post) {
                return $post->scheduled_at->format('Y-m-d');
            })
            ->map(fn($group) => $group->count());

        $missingSlotsDates = [];
        $tempDate = $startDate->copy();
        $todayStr = Carbon::today()->format('Y-m-d');

        while ($tempDate->lte($endDate)) {
            $dateStr = $tempDate->format('Y-m-d');
            $count = $countsByDate->get($dateStr, 0);
            // Only alert for today/future dates
            if ($count < $postsPerDay && $dateStr >= $todayStr) {
                $missingSlotsDates[] = $dateStr;
            }
            $tempDate->addDay();
        }

        $topics = Topic::all()->map(function($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
            ];
        });

        $pages = Page::where('is_active', true)->get();

        return Inertia::render('Calendar/Index', [
            'posts' => $posts,
            'month' => $month,
            'year' => $year,
            'topics' => $topics,
            'missingSlotsDates' => $missingSlotsDates,
            'filters' => $request->only(['status', 'topic_id', 'page_id']),
            'pages' => $pages,
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'days' => 'required|in:1,7,14,30',
            'posts_per_day' => 'required|integer|min:1|max:10',
            'start_date' => 'nullable|date',
            'topic_ids' => 'nullable|array',
            'topic_ids.*' => 'exists:topics,id',
            'media_type' => 'nullable|in:photo,video,both',
            'page_id' => 'nullable|exists:pages,id',
        ]);

        $pageId = $request->input('page_id');

        if ($pageId) {
            $page = Page::findOrFail($pageId);
            $planner = new AIContentPlannerService();
            $plan = $planner->generatePlanForPage($page, [
                'days' => (int)$validated['days'],
                'posts_per_day' => (int)$validated['posts_per_day'],
                'tone' => $page->content_tone,
                'language' => $page->language,
            ]);
            $summary = $planner->createDraftsFromPlan($page, $plan);

            $created = $summary['created_count'] ?? 0;
            return redirect()->route('calendar.index', ['page_id' => $page->id])
                ->with('success', "Auto-generated {$created} drafts for Page '{$page->name}' successfully!");
        }

        // Legacy ContentCalendarService flow
        $options = [
            'posts_per_day' => (int)$validated['posts_per_day'],
            'start_date' => $validated['start_date'] ?? null,
            'topic_ids' => $validated['topic_ids'] ?? null,
            'media_type' => $validated['media_type'] ?? 'both',
        ];

        $service = new ContentCalendarService();
        $summary = $service->generateScheduleForDays((int)$validated['days'], $options);

        $flashType = 'success';
        $message = "Auto-generated {$summary['created']} draft posts successfully! (Skipped: {$summary['skipped']})";

        if (!empty($summary['errors'])) {
            $flashType = 'warning';
            $message .= " Note: " . implode(', ', array_slice($summary['errors'], 0, 3));
        }

        return redirect()->route('calendar.index')
            ->with($flashType, $message);
    }
}
