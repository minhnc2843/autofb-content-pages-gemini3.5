<?php

namespace App\Http\Controllers;

use App\Models\PostQueue;
use App\Models\Topic;
use App\Services\ContentCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $year = (int)$request->input('year', Carbon::now()->year);
        $month = (int)$request->input('month', Carbon::now()->month);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $query = PostQueue::with(['topic', 'mediaItem'])
            ->whereBetween('scheduled_at', [$startDate, $endDate]);

        // Filter: status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter: topic_id
        if ($request->filled('topic_id')) {
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

        // Calculate missing slots (dates in this month where posts < 3)
        // Group by scheduled date
        $countsByDate = PostQueue::whereBetween('scheduled_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($post) {
                return $post->scheduled_at->format('Y-m-d');
            })
            ->map(fn($group) => $group->count());

        $missingSlotsDates = [];
        $tempDate = $startDate->copy();
        while ($tempDate->lte($endDate)) {
            $dateStr = $tempDate->format('Y-m-d');
            $count = $countsByDate->get($dateStr, 0);
            if ($count < 3) {
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

        return Inertia::render('Calendar/Index', [
            'posts' => $posts,
            'month' => $month,
            'year' => $year,
            'topics' => $topics,
            'missingSlotsDates' => $missingSlotsDates,
            'filters' => $request->only(['status', 'topic_id']),
        ]);
    }

    public function generate(Request $request)
    {
        $days = (int)$request->input('days', 7);
        $postsPerDay = (int)$request->input('posts_per_day', 3);

        $service = new ContentCalendarService();
        $count = $service->generateSchedule($days, $postsPerDay);

        return redirect()->route('calendar.index')
            ->with('success', "Auto-generated {$count} draft posts successfully!");
    }
}
