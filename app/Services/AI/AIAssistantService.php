<?php

namespace App\Services\AI;

use App\Models\Page;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiTask;
use App\Models\PendingSecret;
use App\Services\AI\GeminiService;
use App\Services\AI\AIContentPlannerService;
use App\Services\AI\PageRecommendationService;
use App\Services\FacebookPageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AIAssistantService
{
    protected GeminiService $geminiService;
    protected AIContentPlannerService $plannerService;
    protected PageRecommendationService $recommendationService;

    public function __construct()
    {
        $this->geminiService = new GeminiService();
        $this->plannerService = new AIContentPlannerService();
        $this->recommendationService = new PageRecommendationService();
    }

    /**
     * Process message from chatbot, extract intent, and create tasks.
     */
    public function processMessage(string $message, AiChatSession $session, array $pendingSecretIds): string
    {
        $page = $session->page_id ? Page::find($session->page_id) : null;

        // Parse intent (via Gemini if enabled, otherwise fallback heuristics)
        $parsed = $this->parseIntent($message, $page, $pendingSecretIds);

        $intent = $parsed['intent'] ?? 'unknown';
        $parameters = $parsed['parameters'] ?? [];
        $reply = $parsed['reply'] ?? '';

        if (count($pendingSecretIds) > 0) {
            $parameters['pending_secret_id'] = $pendingSecretIds[0];
            $parameters['token_present'] = true;
        }

        // Handle unsupported/unknown intents
        if ($intent === 'unknown' || $intent === 'unsupported') {
            $reply = "I can only plan, analyze, generate drafts, or handle tokens in this phase. I didn't recognize that request. Could you try asking to generate content plan, analyze page, or update token?";
            AiChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $reply,
            ]);
            return $reply;
        }

        // If intent is valid, create a Task in status 'awaiting_confirmation'
        $requiresConfirmation = true;
        
        // Some simple informational intents might not require confirmation
        if (in_array($intent, ['list_pages_missing_token'])) {
            $requiresConfirmation = false;
        }

        $task = AiTask::create([
            'page_id' => $page?->id,
            'chat_session_id' => $session->id,
            'type' => $intent,
            'status' => $requiresConfirmation ? 'awaiting_confirmation' : 'running',
            'user_prompt' => $message,
            'plan_json' => [
                'parameters' => $parameters,
                'steps' => $this->buildPlanSteps($intent, $page, $parameters),
            ],
            'requires_confirmation' => $requiresConfirmation,
        ]);

        if (!$requiresConfirmation) {
            // Execute immediately
            $result = $this->executeConfirmedTask($task);
            if ($result['success']) {
                $task->update(['status' => 'completed', 'result_json' => $result]);
                $reply = "Here is the information: " . ($result['message'] ?? '');
            } else {
                $task->update(['status' => 'failed', 'error_message' => $result['message']]);
                $reply = "Sorry, I failed to complete the task: " . ($result['message'] ?? '');
            }
        } else {
            // Task needs confirmation, tell the user in the reply
            $warningText = count($pendingSecretIds) > 0 ? "\n\n⚠️ *A secret token was detected and redacted. You will need to confirm the token update task in the panel on the right.*" : "";
            $reply .= $warningText . "\n\nI have created a pending task. Please review the plan in the sidebar and click **Confirm** to execute it.";
        }

        // Save assistant message to database
        AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return $reply;
    }

    /**
     * Parse intent using Gemini or heuristic rules.
     */
    public function parseIntent(string $message, ?Page $page, array $pendingSecretIds = []): array
    {
        if ($this->geminiService->isEnabled()) {
            $prompt = "You are the AI Assistant for the Auto FB Content Planner. Your job is to analyze the user message and determine their intent.
The current page context is ID: " . ($page?->id ?? 'None') . ", Name: " . ($page?->name ?? 'None') . ".

Supported intents:
1. 'create_content_plan': User wants a content plan (e.g. 'Tạo kế hoạch 7 ngày...', 'Tạo kế hoạch cho page X...').
   Parameters:
   - days (integer, default 7)
   - posts_per_day (integer, default 3)
   - tone (string, tone of voice, e.g. calm, funny)
   - language (string, e.g. english, vietnamese)

2. 'generate_drafts': User wants to generate draft posts in the queue (e.g. 'Tạo draft ngày mai...', 'Tạo 5 draft video cho page X...').
   Parameters:
   - number_of_posts (integer, default 3)
   - media_type (string: photo, video, or both)
   - language (string)
   - tone (string)

3. 'analyze_page': User wants a page analysis/audit (e.g. 'Phân tích page Phật giáo...', 'Đề xuất nội dung...').
   Parameters:
   - page_id (integer, default current page)

4. 'optimize_schedule': User wants schedule optimization recommendations.
   Parameters:
   - page_id (integer)

5. 'balance_content_mix': User wants adjustments to photo/video mix.
   Parameters:
   - page_id (integer)

6. 'update_page_token': User wants to update the access token. Redacted token shows up as [FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED].
   Parameters:
   - page_name (string, e.g. Nature Healing)
   - token_present (boolean, true if user provided token)
   - validate_after_save (boolean, default true)

7. 'validate_page_token': User wants to check config for a page.
   Parameters:
   - page_name (string, nullable)

8. 'list_pages_missing_token': User wants to find pages without tokens.
   Parameters: none

9. 'validate_all_pages': User wants to check config for all active pages.
   Parameters: none

If the request does not match any of these, set intent to 'unknown'.
If the request describes doing something not listed (like posting directly, deleting posts, rendering video), set intent to 'unsupported'.

You MUST return a JSON object with this exact schema:
{
  \"intent\": \"intent_name_here\",
  \"parameters\": {
     \"days\": 7,
     \"posts_per_day\": 3,
     ...other parameters...
  },
  \"reply\": \"A clear, friendly explanation of the action you have planned, and listing the parameters. Prompt the user to confirm it in the task panel.\"
}
User Message: \"{$message}\"
Ensure output is valid JSON and nothing else.";

            try {
                $result = $this->geminiService->generateText($prompt);
                if (isset($result['text']) && !empty(trim($result['text']))) {
                    $text = trim($result['text']);
                    if (str_starts_with($text, '```json')) {
                        $text = trim(substr($text, 7, -3));
                    } elseif (str_starts_with($text, '```')) {
                        $text = trim(substr($text, 3, -3));
                    }
                    $decoded = json_decode($text, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Gemini intent parsing failed, falling back to heuristics: " . $e->getMessage());
            }
        }

        // Heuristic fallback parser
        $msgLower = strtolower($message);
        $intent = 'unknown';
        $parameters = [];
        $reply = '';

        if (count($pendingSecretIds) > 0 || str_contains($msgLower, 'cập nhật token') || str_contains($msgLower, 'update token') || str_contains($msgLower, '[facebook_page_access_token_redacted]')) {
            $intent = 'update_page_token';
            $parameters = [
                'token_present' => true,
                'validate_after_save' => true,
            ];
            $reply = "I detected that you want to update a Facebook Page access token.";
        } elseif (str_contains($msgLower, 'validate all') || str_contains($msgLower, 'kiểm tra tất cả') || str_contains($msgLower, 'validate_all_pages')) {
            $intent = 'validate_all_pages';
            $reply = "I will validate the configuration for all active Facebook pages.";
        } elseif (str_contains($msgLower, 'kiểm tra token') || str_contains($msgLower, 'validate token') || str_contains($msgLower, 'validate config') || str_contains($msgLower, 'kiểm tra page')) {
            $intent = 'validate_page_token';
            $reply = "I will validate the Facebook Page configuration.";
        } elseif (str_contains($msgLower, 'kế hoạch') || str_contains($msgLower, 'plan') || str_contains($msgLower, 'lịch đăng')) {
            $intent = 'create_content_plan';
            $parameters = [
                'days' => 7,
                'posts_per_day' => 3,
            ];
            $reply = "I will generate a 7-day content plan showing schedule slots, topics, media types, and caption suggestions.";
        } elseif (str_contains($msgLower, 'draft') || str_contains($msgLower, 'bản nháp') || str_contains($msgLower, 'tạo bài')) {
            $intent = 'generate_drafts';
            $parameters = [
                'number_of_posts' => 3,
            ];
            $reply = "I will search Pexels and generate draft posts with captions directly in your posts queue.";
        } elseif (str_contains($msgLower, 'phân tích') || str_contains($msgLower, 'analyze') || str_contains($msgLower, 'đề xuất')) {
            $intent = 'analyze_page';
            $reply = "I will analyze your page performance logs and settings to generate recommendations.";
        } elseif (str_contains($msgLower, 'tối ưu lịch') || str_contains($msgLower, 'optimize schedule')) {
            $intent = 'optimize_schedule';
            $reply = "I will analyze your posting schedule and recommend optimal slots.";
        } elseif (str_contains($msgLower, 'thiếu token') || str_contains($msgLower, 'missing token') || str_contains($msgLower, 'chưa có token')) {
            $intent = 'list_pages_missing_token';
            $reply = "I will find all pages that are missing a Facebook Access token.";
        }

        return [
            'intent' => $intent,
            'parameters' => $parameters,
            'reply' => $reply,
        ];
    }

    /**
     * Build plan steps for display to the user in the preview panel.
     */
    protected function buildPlanSteps(string $intent, ?Page $page, array $parameters): array
    {
        $pageName = $page?->name ?? 'Default/Selected Page';
        
        switch ($intent) {
            case 'update_page_token':
                return [
                    "Locate pending encrypted token in the secure database",
                    "Resolve target Page matching name or ID",
                    "Update target page's access_token field securely",
                    "Run Facebook Graph API validation checks immediately",
                ];
            case 'validate_page_token':
                return [
                    "Fetch encrypted credentials for page: '{$pageName}'",
                    "Send test API request to graph.facebook.com/{version}/{page_id}",
                    "Report validation status and expiry information",
                ];
            case 'validate_all_pages':
                return [
                    "Identify all active Facebook pages",
                    "Test Graph API connection for each page",
                    "Generate a summary status report",
                ];
            case 'create_content_plan':
                $days = $parameters['days'] ?? 7;
                $posts = $parameters['posts_per_day'] ?? 3;
                return [
                    "Analyze profile settings (niche, mix, slot hours) for '{$pageName}'",
                    "Generate a {$days}-day schedule layout ({$posts} posts/day)",
                    "Select active page topics round-robin",
                    "Define caption direction guidelines per slot",
                ];
            case 'generate_drafts':
                $num = $parameters['number_of_posts'] ?? 3;
                return [
                    "Identify active page topics",
                    "Search Pexels stock photos and videos for suitable media",
                    "Generate custom captions for each media item",
                    "Add {$num} new posts to the draft queue for '{$pageName}'",
                ];
            case 'analyze_page':
                return [
                    "Read page settings and profile presets",
                    "Inspect recent posts queue history and success/failure logs",
                    "Generate performance scores and audit recommendations",
                ];
            case 'optimize_schedule':
                return [
                    "Retrieve current posting slots",
                    "Generate recommended posting slots based on niche heuristics",
                ];
            case 'list_pages_missing_token':
                return [
                    "Query pages table for missing access tokens",
                ];
            default:
                return ["Execute request parameters"];
        }
    }

    /**
     * Execute a task after user clicks "Confirm".
     */
    public function executeConfirmedTask(AiTask $task): array
    {
        $parameters = $task->plan_json['parameters'] ?? [];
        $page = $task->page_id ? Page::find($task->page_id) : Page::where('slug', 'default-facebook-page')->first();
        if (!$page) {
            // Lazy resolve default page
            $context = new \App\Services\PageContextService();
            $page = $context->getDefaultPage();
        }

        switch ($task->type) {
            case 'update_page_token':
                $pendingSecretId = $parameters['pending_secret_id'] ?? null;
                if (!$pendingSecretId) {
                    return ['success' => false, 'message' => 'No pending secret found in plan. Please try pasting the token again.'];
                }

                $pending = PendingSecret::find($pendingSecretId);
                if (!$pending || $pending->isExpired() || $pending->isConsumed()) {
                    return ['success' => false, 'message' => 'The pending token has expired or already been consumed. Please submit it again.'];
                }

                // Resolve Page
                $targetPage = null;
                if (!empty($parameters['page_name'])) {
                    $targetPage = Page::where('name', 'like', "%{$parameters['page_name']}%")->first();
                }
                if (!$targetPage) {
                    $targetPage = $page;
                }

                if (!$targetPage) {
                    return ['success' => false, 'message' => 'Could not determine which page to update. Please select a page first.'];
                }

                // Update token (automatically encrypted by Page cast)
                $targetPage->update([
                    'access_token' => $pending->encrypted_value,
                ]);

                // Consume secret
                $pending->update(['consumed_at' => now()]);

                // Validate config if requested
                $validationMsg = "";
                if ($parameters['validate_after_save'] ?? true) {
                    $fbService = new FacebookPageService($targetPage);
                    $valResult = $fbService->validateConfig();
                    if ($valResult['success']) {
                        $targetPage->update([
                            'facebook_page_name' => $valResult['page_name'],
                            'facebook_page_link' => $valResult['page_link'],
                        ]);
                        $validationMsg = " Token validated successfully: page name is {$valResult['page_name']}.";
                    } else {
                        $validationMsg = " WARNING: Token saved, but validation failed: {$valResult['message']}.";
                    }
                }

                return [
                    'success' => true,
                    'message' => "Page '{$targetPage->name}' access token updated successfully.{$validationMsg}",
                    'page_id' => $targetPage->id,
                ];

            case 'validate_page_token':
                $fbService = new FacebookPageService($page);
                $result = $fbService->validateConfig();
                if ($result['success']) {
                    $page->update([
                        'facebook_page_name' => $result['page_name'],
                        'facebook_page_link' => $result['page_link'],
                    ]);
                }
                return $result;

            case 'validate_all_pages':
                $pages = Page::where('is_active', true)->get();
                $report = [];
                $successCount = 0;

                foreach ($pages as $p) {
                    $fbService = new FacebookPageService($p);
                    $res = $fbService->validateConfig();
                    $report[] = [
                        'page_name' => $p->name,
                        'facebook_page_id' => $p->facebook_page_id ?: 'Not Set',
                        'status' => $res['success'] ? 'valid' : 'invalid',
                        'message' => $res['message'],
                    ];
                    if ($res['success']) {
                        $successCount++;
                    }
                }

                return [
                    'success' => true,
                    'message' => "Validated " . count($pages) . " active pages. ({$successCount} valid, " . (count($pages) - $successCount) . " invalid).",
                    'report' => $report,
                ];

            case 'list_pages_missing_token':
                $pages = Page::where('is_active', true)
                    ->get()
                    ->filter(function ($p) {
                        return empty($p->access_token);
                    })
                    ->values();

                if ($pages->isEmpty()) {
                    return [
                        'success' => true,
                        'message' => "All active pages have access tokens configured.",
                    ];
                }

                $listStr = $pages->map(function ($p) {
                    return "- {$p->name} (ID: {$p->id})";
                })->implode("\n");

                return [
                    'success' => true,
                    'message' => "The following active pages do not have access tokens:\n" . $listStr,
                    'pages' => $pages->pluck('id')->toArray(),
                ];

            case 'create_content_plan':
                $plan = $this->plannerService->generatePlanForPage($page, $parameters);
                
                // Save plan in plan_json of the task so user can confirm and generate drafts later if they want
                $task->update([
                    'plan_json' => array_merge($task->plan_json, ['plan' => $plan]),
                ]);

                return [
                    'success' => true,
                    'message' => "Generated " . count($plan) . "-slot content plan for page '{$page->name}'. Preview: Day 1 will focus on topic '{$plan[0]['topic']}' ({$plan[0]['media_type']}) at {$plan[0]['slot']}.",
                    'plan' => $plan,
                ];

            case 'generate_drafts':
                // Check if we have an existing plan saved from a previous step
                $plan = $task->plan_json['plan'] ?? null;
                if (!$plan) {
                    // Create content plan first
                    $days = 1;
                    $numPosts = intval($parameters['number_of_posts'] ?? 3);
                    
                    // Simple heuristic: 1 day with N posts
                    $planParams = array_merge($parameters, [
                        'days' => $dayOffset = max(1, intval(ceil($numPosts / 3))),
                        'posts_per_day' => 3,
                    ]);
                    $plan = $this->plannerService->generatePlanForPage($page, $planParams);
                    $plan = array_slice($plan, 0, $numPosts);
                }

                // Generate drafts from plan
                $plannerResult = $this->plannerService->createDraftsFromPlan($page, $plan);
                return $plannerResult;

            case 'analyze_page':
                $analysis = $this->recommendationService->auditPage($page);
                return [
                    'success' => true,
                    'message' => "Page audit complete. Page Score: {$analysis['page_score']}/100. Feedback: {$analysis['content_mix_feedback']}. Next week suggestion: {$analysis['next_week_plan']}",
                    'analysis' => $analysis,
                ];

            case 'optimize_schedule':
                $analysis = $this->recommendationService->auditPage($page);
                return [
                    'success' => true,
                    'message' => "Posting schedule analysis: Best posting times are estimated at: {$analysis['best_time_guess']}. Reason: {$analysis['content_mix_feedback']}",
                    'best_times' => $analysis['best_time_guess'],
                ];

            default:
                return [
                    'success' => false,
                    'message' => "Unsupported task type: " . $task->type,
                ];
        }
    }
}
