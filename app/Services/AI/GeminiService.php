<?php

namespace App\Services\AI;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AIProviderInterface
{
    /**
     * Get the Gemini API Key.
     */
    public function getApiKey(): ?string
    {
        return Setting::getValue('GEMINI_API_KEY');
    }

    /**
     * Get the Gemini Model name.
     */
    public function getModel(): string
    {
        return Setting::getValue('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash'));
    }

    /**
     * Base HTTP call to Gemini API.
     */
    protected function callGeminiApi(string $prompt, ?string $responseMimeType = null): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            Log::warning('Gemini API key is not configured.');
            return ['error' => 'Gemini API key is not configured. Please set GEMINI_API_KEY in Settings or .env.'];
        }

        $model = $this->getModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        if ($responseMimeType) {
            $payload['generationConfig'] = [
                'responseMimeType' => $responseMimeType
            ];
        }

        try {
            $response = Http::post($url, $payload);

            if ($response->failed()) {
                $err = $response->json()['error']['message'] ?? "HTTP error {$response->status()}";
                Log::error('Gemini API call failed: ' . $err);
                return ['error' => 'Gemini API error: ' . $err];
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return [
                'text' => $text,
                'raw_response' => $data
            ];
        } catch (\Exception $e) {
            Log::error('Gemini connection error: ' . $e->getMessage());
            return ['error' => 'Could not connect to Gemini API: ' . $e->getMessage()];
        }
    }

    public function generateText(string $prompt): array
    {
        return $this->callGeminiApi($prompt);
    }

    public function scorePost(array $postData): array
    {
        $caption = $postData['caption'] ?? '';
        $media = $postData['media'] ?? [];

        $mediaDesc = "";
        if (!empty($media)) {
            $mediaDesc = "Media details: Type: " . ($media['type'] ?? 'unknown') . 
                         ", Photographer: " . ($media['photographer'] ?? 'unknown');
        } else {
            $mediaDesc = "No media, text only.";
        }

        $prompt = "You are an expert social media analyst. Evaluate the following post:
Caption: \"{$caption}\"
{$mediaDesc}

Rate on a scale of 0-100:
- hook_score (Hook quality)
- caption_score (Caption readability/structure)
- cta_score (Call to Action effectiveness)
- hashtag_score (Hashtag selection/no spam)
- media_fit_score (Media relevance)
- overall_score (Weighted average)
Also evaluate risk_level (low, medium, high) and provide strengths, weaknesses, and suggestions.

You MUST return a JSON object with this exact schema:
{
  \"score\": 85,
  \"hook_score\": 90,
  \"caption_score\": 85,
  \"cta_score\": 80,
  \"hashtag_score\": 95,
  \"media_fit_score\": 85,
  \"risk_level\": \"low\",
  \"strengths\": [\"Strength 1\", \"Strength 2\"],
  \"weaknesses\": [\"Weakness 1\"],
  \"suggestions\": [\"Suggestion 1\", \"Suggestion 2\"]
}
Ensure the output is valid JSON and nothing else.";

        $result = $this->callGeminiApi($prompt, 'application/json');

        if (isset($result['error'])) {
            return [
                'score' => 0,
                'hook_score' => 0,
                'caption_score' => 0,
                'cta_score' => 0,
                'hashtag_score' => 0,
                'media_fit_score' => 0,
                'risk_level' => 'low',
                'suggestions' => ['Could not get analysis: ' . $result['error']]
            ];
        }

        $text = trim($result['text'] ?? '');
        if (str_starts_with($text, '```json')) {
            $text = trim(substr($text, 7, -3));
        } elseif (str_starts_with($text, '```')) {
            $text = trim(substr($text, 3, -3));
        }

        try {
            $parsed = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        } catch (\Exception $e) {
            Log::warning("Could not parse scorePost JSON: " . $e->getMessage());
        }

        return [
            'score' => 50,
            'hook_score' => 50,
            'caption_score' => 50,
            'cta_score' => 50,
            'hashtag_score' => 50,
            'media_fit_score' => 50,
            'risk_level' => 'low',
            'strengths' => ['Post has basic content.'],
            'weaknesses' => ['AI returned malformed data.'],
            'suggestions' => ['Review formatting.']
        ];
    }

    public function auditPage(array $pageData): array
    {
        $insights = $pageData['insights'] ?? [];
        $topics = $pageData['topics'] ?? [];
        $recentLogs = $pageData['recent_logs'] ?? [];

        $insightsDesc = "Insights summary:\n";
        foreach ($insights as $metric => $val) {
            $insightsDesc .= "- {$metric}: {$val}\n";
        }

        $topicsDesc = "Configured topics:\n";
        foreach ($topics as $t) {
            $topicsDesc .= "- " . ($t['name'] ?? 'Unknown') . "\n";
        }

        $logsDesc = "Recent posts history:\n";
        foreach ($recentLogs as $log) {
            $logsDesc .= "- Status: " . ($log['status'] ?? 'unknown') . ", caption: \"" . substr($log['caption'] ?? '', 0, 50) . "...\"\n";
        }

        $prompt = "You are a professional auditor. Evaluate this Facebook Page:
{$insightsDesc}
{$topicsDesc}
{$logsDesc}

Rate on a scale of 0-100:
- brand_score
- content_score
- cta_score
- consistency_score
- page_score (Overall performance)
Also provide lists of strengths, weaknesses, and recommendations.

You MUST return a JSON object with this exact schema:
{
  \"page_score\": 75,
  \"brand_score\": 80,
  \"content_score\": 75,
  \"cta_score\": 70,
  \"consistency_score\": 85,
  \"strengths\": [\"Strength 1\", \"Strength 2\"],
  \"weaknesses\": [\"Weakness 1\"],
  \"suggestions\": [\"Suggestion 1\", \"Suggestion 2\"]
}
Ensure output is valid JSON and nothing else.";

        $result = $this->callGeminiApi($prompt, 'application/json');

        if (isset($result['error'])) {
            return [
                'page_score' => 0,
                'brand_score' => 0,
                'content_score' => 0,
                'cta_score' => 0,
                'consistency_score' => 0,
                'suggestions' => ['Could not retrieve audit: ' . $result['error']]
            ];
        }

        $text = trim($result['text'] ?? '');
        if (str_starts_with($text, '```json')) {
            $text = trim(substr($text, 7, -3));
        } elseif (str_starts_with($text, '```')) {
            $text = trim(substr($text, 3, -3));
        }

        try {
            $parsed = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        } catch (\Exception $e) {
            Log::warning("Could not parse auditPage JSON: " . $e->getMessage());
        }

        return [
            'page_score' => 60,
            'brand_score' => 60,
            'content_score' => 60,
            'cta_score' => 60,
            'consistency_score' => 60,
            'strengths' => ['Page has baseline metrics.'],
            'weaknesses' => ['AI returned malformed data.'],
            'suggestions' => ['Review post schedule.']
        ];
    }

    /**
     * Generate 3 caption variants using Gemini AI with preset styles.
     */
    public function generateCaptionVariants(array $topic, array $media, string $language = 'english', string $preset = 'facebook_engagement'): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return [
                'variants' => [
                    "Variant 1 (Local template): Beautiful shot of " . ($topic['name'] ?? 'nature'),
                    "Variant 2 (Local template): Loving this " . ($topic['keyword'] ?? 'nature') . " vibe!",
                    "Variant 3 (Local template): Stunning view captured by " . ($media['photographer'] ?? 'photographer')
                ]
            ];
        }

        $keyword = $topic['keyword'] ?? $topic['name'] ?? 'nature';
        $photographer = $media['photographer'] ?? 'Unknown';
        $mediaType = $media['type'] ?? 'photo';

        $presetDescriptions = [
            'facebook_engagement' => 'Highly engaging, conversational style with an interactive question hook to drive comments.',
            'educational' => 'Informative, teaches a quick fact or tip, professional yet approachable.',
            'spiritual' => 'Inspiring, deep, thoughtful, or mindfulness-oriented.',
            'funny_pet' => 'Humorous, playful, witty, centered around funny pets or lighthearted theme.',
            'short_video' => 'Snappy, viral hook, very short, perfect for video reels.',
            'soft_cta' => 'Focuses on a subtle Call to Action (CTA) like "Share if you agree" or "Tag a friend".'
        ];

        $desc = $presetDescriptions[$preset] ?? $presetDescriptions['facebook_engagement'];

        $prompt = "You are an expert copywriter. Write 3 distinct caption variants in " . strtoupper($language) . " language.
Topic theme: '{$keyword}'
Media info: A {$mediaType} by photographer '{$photographer}' (make sure to credit them).
Style guideline: {$desc}

Requirement: Return a JSON object with this exact schema:
{
  \"variants\": [
    \"Variant 1 text here...\",
    \"Variant 2 text here...\",
    \"Variant 3 text here...\"
  ]
}
Ensure the output is valid JSON and nothing else.";

        $result = $this->callGeminiApi($prompt, 'application/json');
        if (isset($result['error'])) {
            return [
                'variants' => [
                    "Variant 1: Vibe check on {$keyword}.",
                    "Variant 2: Capturing {$keyword} by {$photographer}.",
                    "Variant 3: Let's talk about {$keyword}!"
                ]
            ];
        }

        $text = trim($result['text'] ?? '');
        if (str_starts_with($text, '```json')) {
            $text = trim(substr($text, 7, -3));
        } elseif (str_starts_with($text, '```')) {
            $text = trim(substr($text, 3, -3));
        }

        try {
            $parsed = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['variants'])) {
                return $parsed;
            }
        } catch (\Exception $e) {
            Log::warning("Could not parse caption variants JSON: " . $e->getMessage());
        }

        return [
            'variants' => [
                "Variant 1: Vibe check on {$keyword}.",
                "Variant 2: Capturing {$keyword} by {$photographer}.",
                "Variant 3: Let's talk about {$keyword}!"
            ]
        ];
    }

    public function analyzeMedia(array $mediaData, string $prompt = ''): array
    {
        $mediaDesc = "Media info: Type: " . ($mediaData['type'] ?? 'unknown') . 
                     ", Photographer: " . ($mediaData['photographer'] ?? 'unknown') . 
                     ", URL: " . ($mediaData['url'] ?? '—');
        
        $promptText = "You are a media analyst. Evaluate this stock media file:
{$mediaDesc}
Topic keyword context: \"{$prompt}\"

Rate on a scale of 0-100:
- media_score (Overall visual appeal)
- topic_fit (Relevance to topic keyword)
- visual_quality (Clarity/framing/professional look)
Evaluate risk_level (low, medium, high) and suggestions (whether it should be used for short Reels or Facebook feed posts).

You MUST return a JSON object with this exact schema:
{
  \"media_score\": 85,
  \"topic_fit\": 90,
  \"visual_quality\": 80,
  \"risk_level\": \"low\",
  \"suggestions\": [\"Good for Reels\", \"Photographer credit required\"]
}
Ensure output is valid JSON and nothing else.";

        $result = $this->callGeminiApi($promptText, 'application/json');

        if (isset($result['error'])) {
            return [
                'media_score' => 0,
                'topic_fit' => 0,
                'visual_quality' => 0,
                'risk_level' => 'low',
                'suggestions' => ['Could not analyze media: ' . $result['error']]
            ];
        }

        $text = trim($result['text'] ?? '');
        if (str_starts_with($text, '```json')) {
            $text = trim(substr($text, 7, -3));
        } elseif (str_starts_with($text, '```')) {
            $text = trim(substr($text, 3, -3));
        }

        try {
            $parsed = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        } catch (\Exception $e) {
            Log::warning("Could not parse analyzeMedia JSON: " . $e->getMessage());
        }

        return [
            'media_score' => 70,
            'topic_fit' => 70,
            'visual_quality' => 70,
            'risk_level' => 'low',
            'suggestions' => ['Approved for publication.']
        ];
    }

    /**
     * Propose a weekly content strategy theme and categories plan.
     */
    public function generateStrategy(array $topicsList): array
    {
        $topicsStr = "";
        foreach ($topicsList as $t) {
            $topicsStr .= "- " . ($t['name'] ?? 'Unknown') . " (keyword: " . ($t['keyword'] ?? 'unknown') . ")\n";
        }

        $prompt = "You are a social media strategist. Generate a weekly content strategy (7-day strategy) based on these topics:
{$topicsStr}

Group the strategy into these content categories:
- educational (tips, facts)
- spiritual (inspiration, mindfulness)
- funny (pet humor, lighthearted)
- question (polls, interactive Qs)
- story (narrative, case study)
- viral hook (attention grabber)
- soft CTA (community engagement)

You MUST return a JSON object with this exact schema:
{
  \"strategy_title\": \"Weekly Strategy Theme\",
  \"overview\": \"Strategic overview paragraph...\",
  \"daily_plan\": [
    {
      \"day\": \"Day 1\",
      \"category\": \"educational\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    },
    {
      \"day\": \"Day 2\",
      \"category\": \"funny\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    },
    {
      \"day\": \"Day 3\",
      \"category\": \"spiritual\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    },
    {
      \"day\": \"Day 4\",
      \"category\": \"question\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    },
    {
      \"day\": \"Day 5\",
      \"category\": \"story\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    },
    {
      \"day\": \"Day 6\",
      \"category\": \"viral_hook\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    },
    {
      \"day\": \"Day 7\",
      \"category\": \"soft_cta\",
      \"focus\": \"Key focus topic...\",
      \"prompt_suggestion\": \"Suggested prompt details...\"
    }
  ],
  \"category_distribution\": {
    \"educational\": 1,
    \"funny\": 1,
    \"spiritual\": 1,
    \"question\": 1,
    \"story\": 1,
    \"viral_hook\": 1,
    \"soft_cta\": 1
  }
}
Ensure output is valid JSON (exactly 7 items in daily_plan) and nothing else.";

        $result = $this->callGeminiApi($prompt, 'application/json');

        if (isset($result['error'])) {
            return [
                'strategy_title' => 'Default Content Strategy',
                'overview' => 'Could not retrieve strategy: ' . $result['error'],
                'daily_plan' => [],
                'category_distribution' => []
            ];
        }

        $text = trim($result['text'] ?? '');
        if (str_starts_with($text, '```json')) {
            $text = trim(substr($text, 7, -3));
        } elseif (str_starts_with($text, '```')) {
            $text = trim(substr($text, 3, -3));
        }

        try {
            $parsed = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        } catch (\Exception $e) {
            Log::warning("Could not parse generateStrategy JSON: " . $e->getMessage());
        }

        return [
            'strategy_title' => 'Default Content Strategy',
            'overview' => 'Fallback template strategy due to response parsing issues.',
            'daily_plan' => [],
            'category_distribution' => []
        ];
    }
}
