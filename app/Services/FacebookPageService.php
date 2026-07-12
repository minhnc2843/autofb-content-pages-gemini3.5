<?php

namespace App\Services;

use App\Models\PostPublishLog;
use App\Models\PostQueue;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FacebookPageService
{
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

        try {
            $response = Http::post("{$baseUrl}/{$pageId}/photos", [
                'url' => $post->mediaItem->url,
                'caption' => $post->caption,
                'published' => true,
                'access_token' => $token,
            ]);

            $responseData = $response->json();

            $this->logAction($post, 'publish_photo', $response->successful(), [
                'page_id' => $pageId,
                'endpoint' => "/{$pageId}/photos",
                'media_url' => $post->mediaItem->url,
                'caption_preview' => substr($post->caption, 0, 100),
            ], $this->sanitizeResponse($responseData),
                $response->failed() ? $this->parseGraphError($response) : null
            );

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish photo post: ' . $this->parseGraphError($response),
                ];
            }

            // Facebook returns 'id' for photos, or 'post_id'
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
     * Publish a video post — not supported in Phase 2.
     */
    public function publishVideoPost(PostQueue $post): array
    {
        $this->logAction($post, 'publish_video', false, [
            'reason' => 'Video publishing disabled in Phase 2',
        ], null, 'Facebook video publishing is disabled in Phase 2. Use Phase 2.1 for video upload support.');

        return [
            'success' => false,
            'message' => 'Facebook video publishing is disabled in Phase 2. Use Phase 2.1 for video upload support.',
        ];
    }

    /**
     * Main publish method — handles fake/real mode routing.
     */
    public function publishPost(PostQueue $post): array
    {
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
                    'facebook_post_id' => $result['facebook_post_id'] ?? null,
                    'error_message' => null,
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
        $post->update([
            'status' => 'published_fake',
            'error_message' => null,
        ]);

        $this->logAction($post, 'publish_due', true, [
            'caption_preview' => substr($post->caption, 0, 100),
        ], ['mode' => 'fake', 'post_id' => $post->id], null);

        Log::info("Post #{$post->id} fake-published", [
            'post_id' => $post->id,
            'topic_id' => $post->topic_id,
            'scheduled_at' => $post->scheduled_at,
        ]);

        return [
            'success' => true,
            'message' => 'Post fake-published successfully (FACEBOOK_PUBLISH_MODE=fake).',
            'mode' => 'fake',
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

    /**
     * Parse error message from Facebook Graph API response.
     */
    protected function parseGraphError($response): string
    {
        $data = $response->json();
        if (isset($data['error']['message'])) {
            return $data['error']['message'];
        }
        return "HTTP {$response->status()} error";
    }

    /**
     * Sanitize response to remove any sensitive data before logging.
     */
    protected function sanitizeResponse(?array $data): ?array
    {
        if (!$data) return null;
        unset($data['access_token']);
        return $data;
    }
}
