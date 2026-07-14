<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\PageInsight;
use App\Models\PostQueue;
use App\Models\Topic;
use App\Services\AI\GeminiService;
use App\Services\FacebookPageService;
use Illuminate\Http\Request;

class PageInsightController extends Controller
{
    /**
     * Trigger synchronization of Page Insights from Facebook API.
     */
    public function sync(Request $request)
    {
        $fbService = new FacebookPageService();
        $result = $fbService->syncPageInsights();

        if ($result['success']) {
            return redirect()->route('dashboard')
                ->with('success', $result['message']);
        }

        return redirect()->route('dashboard')
            ->with('error', $result['message']);
    }

    /**
     * Run Gemini AI Page Audit using current insights and topics.
     */
    public function audit(Request $request)
    {
        $gemini = new GeminiService();

        if (!$gemini->isEnabled()) {
            return redirect()->route('dashboard')
                ->with('error', 'Gemini AI features are currently disabled. Please enable it in Settings.');
        }

        // Validate API Key
        if (empty($gemini->getApiKey())) {
            return redirect()->route('dashboard')
                ->with('error', 'Gemini API key is not configured. Please set GEMINI_API_KEY in Settings.');
        }

        // Fetch recent insights
        $impressions = PageInsight::where('metric', 'page_impressions')->orderBy('fetched_date', 'desc')->limit(7)->get();
        $engagement = PageInsight::where('metric', 'page_post_engagements')->orderBy('fetched_date', 'desc')->limit(7)->get();
        $followers = PageInsight::where('metric', 'page_fans')->orderBy('fetched_date', 'desc')->first();

        // Format insights data for audit input
        $insightsData = [
            'recent_impressions_sum' => $impressions->sum(function ($item) {
                return $item->values_json['value'] ?? 0;
            }),
            'recent_engagement_sum' => $engagement->sum(function ($item) {
                return $item->values_json['value'] ?? 0;
            }),
            'total_followers' => $followers->values_json['value'] ?? 0,
        ];

        // Fetch topics config
        $topics = Topic::all()->map(function ($t) {
            return [
                'name' => $t->name,
                'is_active' => $t->is_active,
            ];
        })->toArray();

        // Fetch recent publishing logs
        $recentLogs = PostQueue::whereIn('status', ['published', 'published_fake', 'failed'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'status' => $p->status,
                    'caption' => $p->caption,
                ];
            })->toArray();

        $pageData = [
            'insights' => $insightsData,
            'topics' => $topics,
            'recent_logs' => $recentLogs,
        ];

        try {
            $result = $gemini->auditPage($pageData);

            if (isset($result['error'])) {
                return redirect()->route('dashboard')
                    ->with('error', 'AI Audit failed: ' . $result['error']);
            }

            // Save to database
            AiAnalysis::updateOrCreate(
                [
                    'target_type' => 'page_insight',
                    'target_id' => 0, // 0 represents the overall page
                    'provider' => 'gemini',
                ],
                [
                    'score' => $result['page_score'] ?? 50,
                    'result_json' => $result,
                    'raw_response' => json_encode($result['raw_response'] ?? []),
                ]
            );

            return redirect()->route('dashboard')
                ->with('success', 'AI Page Audit completed successfully! (Score: ' . ($result['page_score'] ?? 50) . ')');

        } catch (\Exception $e) {
            return redirect()->route('dashboard')
                ->with('error', 'AI Audit error: ' . $e->getMessage());
        }
    }
}
