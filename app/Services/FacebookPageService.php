<?php

namespace App\Services;

use App\Models\PostPublishLog;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Models\PageInsight;
use App\Models\Page;
use App\Services\PageContextService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FacebookPageService
{
    protected ?Page $page = null;
    protected PageContextService $contextService;

    public function __construct(?Page $page = null)
    {
        $this->page = $page;
        $this->contextService = new PageContextService();
    }

    /**
     * Get the Meta Graph API base URL.
     */
    public function getGraphBaseUrl(): string
    {
        $version = Setting::getValue('META_GRAPH_VERSION', env('META_GRAPH_VERSION', 'v25.0'));
        return "https://graph.facebook.com/{$version}";
    }

    /**
     * Get the Facebook Page ID from settings or env.
     */
    public function getPageId(): string
    {
        if ($this->page) {
            $credentials = $this->contextService->getPageCredential($this->page);
            if (!empty($credentials['facebook_page_id'])) {
                return $credentials['facebook_page_id'];
            }
        }
        $pageId = Setting::getValue('FACEBOOK_PAGE_ID');
        if (empty($pageId)) {
            throw new RuntimeException(
                'Facebook Page ID is not configured. Please set it in Settings or .env (FACEBOOK_PAGE_ID).'
            );
        }
        return $pageId;
    }

    /**
     * Get the Facebook Page Access Token from settings or env.
     */
    public function getPageAccessToken(): string
    {
        if ($this->page) {
            $credentials = $this->contextService->getPageCredential($this->page);
            if (!empty($credentials['access_token'])) {
                return $credentials['access_token'];
            }
        }
        $token = Setting::getValue('FACEBOOK_PAGE_ACCESS_TOKEN');
        if (empty($token)) {
            throw new RuntimeException(
                'Facebook Page Access Token is not configured. Please set it in Settings or .env (FACEBOOK_PAGE_ACCESS_TOKEN).'
            );
        }
        return $token;
    }

    /**
     * Get the current publish mode (fake or real).
     */
    public function getPublishMode(): string
    {
        if ($this->page) {
            $credentials = $this->contextService->getPageCredential($this->page);
            if (!empty($credentials['publish_mode'])) {
                return $credentials['publish_mode'];
            }
        }
        return Setting::getValue('FACEBOOK_PUBLISH_MODE', env('FACEBOOK_PUBLISH_MODE', 'fake'));
    }

    /**
     * Validate Facebook Page configuration by calling the Graph API.
     */
    public function validateConfig(): array
    {
        try {
            $pageId = $this->getPageId();
            $token = $this->getPageAccessToken();
            $baseUrl = $this->getGraphBaseUrl();

            $response = Http::get("{$baseUrl}/{$pageId}", [
                'fields' => 'id,name,link',
                'access_token' => $token,
            ]);

            $this->logAction(null, 'validate_config', $response->successful(), [
                'page_id' => $pageId,
                'endpoint' => "/{$pageId}?fields=id,name,link",
            ], $response->successful() ? $response->json() : null,
                $response->failed() ? $this->parseGraphError($response) : null
            );

            if ($response->status() === 400) {
                return ['success' => false, 'message' => 'Invalid request. Please check your Page ID.'];
            }
            if ($response->status() === 401 || $response->status() === 403) {
                return ['success' => false, 'message' => 'Invalid or expired Page Access Token. Please update your token.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Rate limit exceeded. Please try again later.'];
            }
            if ($response->failed()) {
                return ['success' => false, 'message' => 'Facebook API error: ' . $this->parseGraphError($response)];
            }

            $data = $response->json();
            return [
                'success' => true,
                'page_id' => $data['id'] ?? $pageId,
                'page_name' => $data['name'] ?? 'Unknown',
                'page_link' => $data['link'] ?? null,
                'message' => 'Facebook Page configuration is valid.',
            ];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['success' => false, 'message' => 'Network error: Could not connect to Facebook API.'];
        }
    }

    /**
     * Publish a text-only post to the Facebook Page.
     */
    public function publishTextPost(PostQueue $post): array
    {
        $pageId = $this->getPageId();
        $token = $this->getPageAccessToken();
        $baseUrl = $this->getGraphBaseUrl();

        try {
            $response = Http::post("{$baseUrl}/{$pageId}/feed", [
                'message' => $post->caption,
                'access_token' => $token,
            ]);

            $responseData = $response->json();

            $this->logAction($post, 'publish_text', $response->successful(), [
                'page_id' => $pageId,
                'endpoint' => "/{$pageId}/feed",
                'caption_preview' => substr($post->caption, 0, 100),
            ], $this->sanitizeResponse($responseData),
                $response->failed() ? $this->parseGraphError($response) : null
            );

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish text post: ' . $this->parseGraphError($response),
                ];
            }

            return [
                'success' => true,
                'facebook_post_id' => $responseData['id'] ?? $responseData['post_id'] ?? null,
                'message' => 'Text post published successfully.',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->logAction($post, 'publish_text', false, [
                'page_id' => $pageId,
            ], null, 'Network error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Network error: Could not connect to Facebook API.'];
        }
    }

    /**
     * Publish a photo post to the Facebook Page.
     */
    public function publishPhotoPost(PostQueue $post): array
    {
        $post->load('mediaItem');

        if (!$post->mediaItem || $post->mediaItem->type !== 'photo') {
            return ['success' => false, 'message' => 'Post does not have a valid photo media item.'];
        }

        $pageId = $this->getPageId();
        $token = $this->getPageAccessToken();
        $baseUrl = $this->getGraphBaseUrl();

        $photoUrl = $this->getOptimizedPhotoUrl($post->mediaItem->url, 1600);

        try {
            $response = Http::post("{$baseUrl}/{$pageId}/photos", [
                'url' => $photoUrl,
                'caption' => $post->caption,
                'published' => true,
                'access_token' => $token,
            ]);

            $responseData = $response->json();
            $errorMsg = $response->failed() ? $this->parseGraphError($response) : null;

            $this->logAction($post, 'publish_photo', $response->successful(), [
                'page_id' => $pageId,
                'endpoint' => "/{$pageId}/photos",
                'media_url' => $photoUrl,
                'caption_preview' => substr($post->caption, 0, 100),
            ], $this->sanitizeResponse($responseData), $errorMsg);

            // Check if failed and matches fallback condition:
            // "reduce the amount of data" or code 1
            $shouldRetry = false;
            if ($response->failed()) {
                $errorData = $responseData['error'] ?? [];
                $code = $errorData['code'] ?? null;
                $message = $errorData['message'] ?? '';
                if ($code == 1 || str_contains(strtolower($message), 'reduce the amount of data')) {
                    $shouldRetry = true;
                }
            }

            if ($shouldRetry) {
                // Retry with width 1200
                $retryUrl = $this->getOptimizedPhotoUrl($post->mediaItem->url, 1200);

                $retryResponse = Http::post("{$baseUrl}/{$pageId}/photos", [
                    'url' => $retryUrl,
                    'caption' => $post->caption,
                    'published' => true,
                    'access_token' => $token,
                ]);

                $retryResponseData = $retryResponse->json();
                $retryErrorMsg = $retryResponse->failed() ? $this->parseGraphError($retryResponse) : null;

                $this->logAction($post, 'publish_photo_retry_compressed', $retryResponse->successful(), [
                    'page_id' => $pageId,
                    'endpoint' => "/{$pageId}/photos",
                    'media_url' => $retryUrl,
                    'caption_preview' => substr($post->caption, 0, 100),
                ], $this->sanitizeResponse($retryResponseData), $retryErrorMsg);

                if ($retryResponse->successful()) {
                    $fbPostId = $retryResponseData['post_id'] ?? $retryResponseData['id'] ?? null;
                    return [
                        'success' => true,
                        'facebook_post_id' => $fbPostId,
                        'message' => 'Photo post published successfully after compressed retry.',
                    ];
                }

                // Failed retry: return both errors
                return [
                    'success' => false,
                    'message' => "Failed to publish photo post. First attempt: {$errorMsg}. Retry compressed: {$retryErrorMsg}",
                ];
            }

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish photo post: ' . $errorMsg,
                ];
            }

            $fbPostId = $responseData['post_id'] ?? $responseData['id'] ?? null;

            return [
                'success' => true,
                'facebook_post_id' => $fbPostId,
                'message' => 'Photo post published successfully.',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->logAction($post, 'publish_photo', false, [
                'page_id' => $pageId,
            ], null, 'Network error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Network error: Could not connect to Facebook API.'];
        }
    }

    /**
     * Publish a video post.
     */
    public function publishVideoPost(PostQueue $post): array
    {
        $post->load('mediaItem');

        if (!$post->mediaItem || $post->mediaItem->type !== 'video') {
            return ['success' => false, 'message' => 'Post does not have a valid video media item.'];
        }

        $mode = $this->getPublishMode();
        if ($mode === 'fake') {
            $timestamp = time();
            $fakeId = "fake_video_{$post->id}_{$timestamp}";

            if (!$post->publish_started_at) {
                $post->increment('publish_attempts');
                $post->update(['publish_started_at' => now()]);
            }

            $post->update([
                'status' => 'published_fake',
                'facebook_post_id' => $fakeId,
                'published_at' => now(),
                'error_message' => null,
            ]);

            $this->logAction($post, 'publish_video', true, [
                'caption_preview' => substr($post->caption, 0, 100),
            ], ['mode' => 'fake', 'facebook_post_id' => $fakeId], null);

            return [
                'success' => true,
                'facebook_post_id' => $fakeId,
                'message' => 'Video post fake-published successfully.',
                'mode' => 'fake',
            ];
        }

        // Real publish mode
        $pageId = $this->getPageId();
        $token = $this->getPageAccessToken();
        $baseUrl = $this->getGraphBaseUrl();

        if (!$post->publish_started_at) {
            $post->increment('publish_attempts');
            $post->update(['publish_started_at' => now()]);
        }

        // Check video size limit
        $maxMb = (int) Setting::getValue('FACEBOOK_VIDEO_MAX_MB', env('FACEBOOK_VIDEO_MAX_MB', '100'));
        $maxBytes = $maxMb * 1024 * 1024;
        $contentLength = null;

        try {
            $headResponse = Http::timeout(5)->head($post->mediaItem->url);
            if ($headResponse->successful()) {
                $contentLength = $headResponse->header('Content-Length');
            }
        } catch (\Exception $e) {
            Log::warning("Could not check video size for Post #{$post->id}: " . $e->getMessage());
        }

        if ($contentLength !== null) {
            if ($contentLength > $maxBytes) {
                $errMsg = "Video size exceeds maximum limit of {$maxMb}MB.";
                $this->logAction($post, 'publish_video', false, [
                    'page_id' => $pageId,
                    'media_url' => $post->mediaItem->url,
                ], null, $errMsg);

                return [
                    'success' => false,
                    'message' => $errMsg,
                ];
            }
        } else {
            Log::warning("Could not determine video size for Post #{$post->id}. Proceeding with remote upload anyway.");
        }

        try {
            $uploadMode = Setting::getValue('FACEBOOK_VIDEO_UPLOAD_MODE', env('FACEBOOK_VIDEO_UPLOAD_MODE', 'remote_url'));

            if ($uploadMode === 'local_download') {
                return $this->publishVideoPostLocal($post, $pageId, $token, $baseUrl);
            }

            // Default: remote_url
            $response = Http::post("{$baseUrl}/{$pageId}/videos", [
                'file_url' => $post->mediaItem->url,
                'description' => $post->caption,
                'access_token' => $token,
            ]);

            $responseData = $response->json();

            $this->logAction($post, 'publish_video', $response->successful(), [
                'page_id' => $pageId,
                'endpoint' => "/{$pageId}/videos",
                'media_url' => $post->mediaItem->url,
                'caption_preview' => substr($post->caption, 0, 100),
            ], $this->sanitizeResponse($responseData),
                $response->failed() ? $this->parseGraphError($response) : null
            );

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish video post: ' . $this->parseGraphError($response),
                ];
            }

            // Parse ID
            $fbPostId = $responseData['post_id'] ?? $responseData['id'] ?? $responseData['video_id'] ?? null;

            if (!$fbPostId) {
                return [
                    'success' => false,
                    'message' => 'Facebook video published but no post id returned.',
                ];
            }

            $post->update(['published_at' => now()]);

            return [
                'success' => true,
                'facebook_post_id' => $fbPostId,
                'message' => 'Video post published successfully.',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->logAction($post, 'publish_video', false, [
                'page_id' => $pageId,
            ], null, 'Network error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Network error: Could not connect to Facebook API.'];
        }
    }

    /**
     * Publish video using local download method - placeholder for Phase 2.2.
     */
    protected function publishVideoPostLocal(PostQueue $post, string $pageId, string $token, string $baseUrl): array
    {
        $tempPath = storage_path('app/temp/' . uniqid() . '.mp4');
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        try {
            $downloadResponse = Http::sink($tempPath)->get($post->mediaItem->url);
            if ($downloadResponse->failed()) {
                $errMsg = 'Failed to download video for local upload: HTTP ' . $downloadResponse->status();
                $this->logAction($post, 'publish_video_local', false, [], null, $errMsg);
                return [
                    'success' => false,
                    'message' => $errMsg,
                ];
            }

            $response = Http::attach(
                'source',
                fopen($tempPath, 'r'),
                'video.mp4'
            )->post("{$baseUrl}/{$pageId}/videos", [
                'description' => $post->caption,
                'access_token' => $token,
            ]);

            $responseData = $response->json();

            $this->logAction($post, 'publish_video_local', $response->successful(), [
                'page_id' => $pageId,
                'endpoint' => "/{$pageId}/videos (local)",
                'caption_preview' => substr($post->caption, 0, 100),
            ], $this->sanitizeResponse($responseData),
                $response->failed() ? $this->parseGraphError($response) : null
            );

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish video post via local upload: ' . $this->parseGraphError($response),
                ];
            }

            $fbPostId = $responseData['post_id'] ?? $responseData['id'] ?? $responseData['video_id'] ?? null;
            if (!$fbPostId) {
                return [
                    'success' => false,
                    'message' => 'Facebook video published via local upload but no post id returned.',
                ];
            }

            $post->update(['published_at' => now()]);

            return [
                'success' => true,
                'facebook_post_id' => $fbPostId,
                'message' => 'Video post published successfully via local upload.',
            ];
        } catch (\Exception $e) {
            $this->logAction($post, 'publish_video_local', false, [
                'page_id' => $pageId,
            ], null, 'Local upload error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Local video upload failed: ' . $e->getMessage(),
            ];
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    /**
     * Main publish method — handles fake/real mode routing.
     */
    public function publishPost(PostQueue $post): array
    {
        if ($post->page_id) {
            $this->page = Page::find($post->page_id);
        }

        $post->load('mediaItem');
        $mediaType = $post->mediaItem?->type;

        if ($mediaType === 'video' && Setting::getValue('FACEBOOK_PUBLISH_AS_REEL', env('FACEBOOK_PUBLISH_AS_REEL', 'false')) === 'true') {
            $reelsService = new FacebookReelsService($this);
            $result = $reelsService->publishReelPost($post);

            if ($result['success']) {
                $post->update([
                    'status' => ($result['mode'] ?? '') === 'fake' ? 'published_fake' : 'published',
                    'facebook_post_id' => $result['facebook_post_id'] ?? $post->facebook_post_id,
                    'error_message' => null,
                    'published_at' => now(),
                ]);
            } else {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['message'],
                ]);
            }

            return $result;
        }

        $mode = $this->getPublishMode();

        if ($mode === 'fake') {
            return $this->fakePublish($post);
        }

        // Real mode
        if ($post->status !== 'approved') {
            return ['success' => false, 'message' => 'Only approved posts can be published.'];
        }

        $post->load('mediaItem');
        $mediaType = $post->mediaItem?->type;

        $post->increment('publish_attempts');
        $post->update(['publish_started_at' => now()]);

        try {
            if ($mediaType === 'photo') {
                $result = $this->publishPhotoPost($post);
            } elseif ($mediaType === 'video') {
                $result = $this->publishVideoPost($post);
            } else {
                $result = $this->publishTextPost($post);
            }

            if ($result['success']) {
                $post->update([
                    'status' => 'published',
                    'facebook_post_id' => $result['facebook_post_id'] ?? $post->facebook_post_id,
                    'error_message' => null,
                    'published_at' => now(),
                ]);
            } else {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['message'],
                ]);
            }

            return $result;
        } catch (RuntimeException $e) {
            $post->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->logAction($post, 'publish_due', false, [], null, $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fake publish — no API call, just status change.
     */
    protected function fakePublish(PostQueue $post): array
    {
        $post->load('mediaItem');
        $type = $post->mediaItem?->type ?? 'text';
        $timestamp = time();
        $fakeId = "fake_{$type}_{$post->id}_{$timestamp}";

        $post->increment('publish_attempts');
        $post->update([
            'publish_started_at' => now(),
            'published_at' => now(),
            'status' => 'published_fake',
            'facebook_post_id' => $fakeId,
            'error_message' => null,
        ]);

        $this->logAction($post, "publish_{$type}", true, [
            'caption_preview' => substr($post->caption, 0, 100),
        ], ['mode' => 'fake', 'post_id' => $post->id, 'facebook_post_id' => $fakeId], null);

        Log::info("Post #{$post->id} fake-published as {$type}", [
            'post_id' => $post->id,
            'topic_id' => $post->topic_id,
            'scheduled_at' => $post->scheduled_at,
        ]);

        return [
            'success' => true,
            'message' => 'Post fake-published successfully (FACEBOOK_PUBLISH_MODE=fake).',
            'mode' => 'fake',
            'facebook_post_id' => $fakeId,
        ];
    }

    /**
     * Log a publish action to PostPublishLog.
     * IMPORTANT: Never log access tokens.
     */
    protected function logAction(
        ?PostQueue $post,
        string $action,
        bool $success,
        array $requestSummary = [],
        ?array $responseJson = null,
        ?string $errorMessage = null
    ): void {
        // Ensure no tokens leak into logs
        unset($requestSummary['access_token']);
        if ($responseJson) {
            unset($responseJson['access_token']);
        }

        PostPublishLog::create([
            'page_id' => $this->page?->id,
            'post_queue_id' => $post?->id,
            'mode' => $this->getPublishMode(),
            'provider' => 'facebook',
            'action' => $action,
            'status' => $success ? 'success' : 'failed',
            'request_summary' => $requestSummary ?: null,
            'response_json' => $responseJson,
            'error_message' => $errorMessage,
        ]);
    }

    protected function parseGraphError($response): string
    {
        $data = $response->json();
        $error = $data['error'] ?? [];
        $message = $error['message'] ?? "HTTP {$response->status()} error";
        $code = $error['code'] ?? null;
        $subcode = $error['error_subcode'] ?? null;
        $type = $error['type'] ?? null;

        return trim($message . 
            ($code ? " (code: {$code})" : '') . 
            ($subcode ? ", subcode: {$subcode}" : '') . 
            ($type ? ", type: {$type}" : '')
        );
    }

    protected function sanitizeResponse(?array $data): ?array
    {
        if (!$data) return null;
        unset($data['access_token']);
        return $data;
    }

    /**
     * Get optimized compressed photo URL for Facebook publishing.
     */
    protected function getOptimizedPhotoUrl(string $url, int $width = 1600): string
    {
        if (str_contains($url, 'images.pexels.com')) {
            $base = strtok($url, '?');
            return $base . '?auto=compress&cs=tinysrgb&w=' . $width;
        }
        return $url;
    }

    /**
     * Sync page insights from Facebook Graph API or generate mock data in fake mode.
     */
    public function syncPageInsights(): array
    {
        $mode = $this->getPublishMode();
        if ($mode === 'fake') {
            // Generate mock insights for the last 7 days
            $metrics = ['page_impressions', 'page_post_engagements', 'page_fans'];
            $today = now();
            for ($i = 0; $i < 7; $i++) {
                $date = $today->copy()->subDays($i)->format('Y-m-d');
                foreach ($metrics as $metric) {
                    $val = match ($metric) {
                        'page_impressions' => rand(500, 3000),
                        'page_post_engagements' => rand(50, 400),
                        'page_fans' => 1000 + ((7 - $i) * rand(2, 10)),
                    };
                    PageInsight::updateOrCreate(
                        [
                            'page_id' => $this->page?->id,
                            'metric' => $metric,
                            'period' => $metric === 'page_fans' ? 'lifetime' : 'day',
                            'fetched_date' => $date,
                        ],
                        [
                            'values_json' => ['value' => $val],
                        ]
                    );
                }
            }

            $this->logAction(null, 'sync_insights', true, [], ['mode' => 'fake'], null);

            return [
                'success' => true,
                'message' => 'Synced mock page insights successfully (fake mode).',
                'mode' => 'fake',
            ];
        }

        // Real mode
        try {
            $pageId = $this->getPageId();
            $token = $this->getPageAccessToken();
            $baseUrl = $this->getGraphBaseUrl();

            $response = Http::get("{$baseUrl}/{$pageId}/insights", [
                'metric' => 'page_impressions,page_post_engagements,page_fans',
                'period' => 'day',
                'access_token' => $token,
            ]);

            $this->logAction(null, 'sync_insights', $response->successful(), [
                'page_id' => $pageId,
            ], $response->successful() ? $response->json() : null,
                $response->failed() ? $this->parseGraphError($response) : null
            );

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to sync insights: ' . $this->parseGraphError($response),
                ];
            }

            $data = $response->json()['data'] ?? [];

            foreach ($data as $metricItem) {
                $name = $metricItem['name'] ?? null;
                $period = $metricItem['period'] ?? 'day';
                $values = $metricItem['values'] ?? [];

                if (!$name) continue;

                foreach ($values as $valItem) {
                    $endTime = $valItem['end_time'] ?? null;
                    $val = $valItem['value'] ?? 0;

                    if (!$endTime) continue;

                    // Format end_time to Y-m-d
                    $date = date('Y-m-d', strtotime($endTime));

                    PageInsight::updateOrCreate(
                        [
                            'page_id' => $this->page?->id,
                            'metric' => $name,
                            'period' => $period,
                            'fetched_date' => $date,
                        ],
                        [
                            'values_json' => ['value' => $val],
                        ]
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Synced Page Insights successfully from Facebook.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error syncing insights: ' . $e->getMessage(),
            ];
        }
    }
}
