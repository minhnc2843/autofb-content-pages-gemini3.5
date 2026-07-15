<?php
 
namespace App\Services;
 
use App\Models\PostQueue;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
 
class FacebookReelsService
 {
    protected FacebookPageService $pageService;
 
    public function __construct(?FacebookPageService $pageService = null)
    {
        $this->pageService = $pageService ?: new FacebookPageService();
    }
 
    /**
     * Publish a video post as a Reel to the Facebook Page.
     */
    public function publishReelPost(PostQueue $post): array
    {
        $post->load('mediaItem');
 
        if (!$post->mediaItem || $post->mediaItem->type !== 'video') {
            return ['success' => false, 'message' => 'Post does not have a valid video media item for Reel.'];
        }
 
        $mode = $this->pageService->getPublishMode();
        if ($mode === 'fake') {
            $timestamp = time();
            $fakeId = "fake_reel_{$post->id}_{$timestamp}";
 
            // In fake mode, we mark the post as published
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
 
            // Log the action using the page service's logger
            $this->logAction($post, 'publish_reel', true, [
                'caption_preview' => substr($post->caption, 0, 100),
            ], ['mode' => 'fake', 'facebook_post_id' => $fakeId], null);
 
            return [
                'success' => true,
                'facebook_post_id' => $fakeId,
                'message' => 'Reel fake-published successfully.',
                'mode' => 'fake',
            ];
        }
 
        // Real mode Reels publishing
        $pageId = $this->pageService->getPageId();
        $token = $this->pageService->getPageAccessToken();
        $baseUrl = $this->pageService->getGraphBaseUrl();
 
        if (!$post->publish_started_at) {
            $post->increment('publish_attempts');
            $post->update(['publish_started_at' => now()]);
        }
 
        // Check size limit
        $maxMb = (int) Setting::getValue('FACEBOOK_VIDEO_MAX_MB', env('FACEBOOK_VIDEO_MAX_MB', '100'));
        $maxBytes = $maxMb * 1024 * 1024;
        $contentLength = null;
 
        try {
            $headResponse = Http::timeout(5)->head($post->mediaItem->url);
            if ($headResponse->successful()) {
                $contentLength = $headResponse->header('Content-Length');
            }
        } catch (\Exception $e) {
            Log::warning("Could not check video size for Reel Post #{$post->id}: " . $e->getMessage());
        }
 
        if ($contentLength !== null && $contentLength > $maxBytes) {
            $errMsg = "Reel video size exceeds maximum limit of {$maxMb}MB.";
            $this->logAction($post, 'publish_reel', false, [
                'page_id' => $pageId,
                'media_url' => $post->mediaItem->url,
            ], null, $errMsg);
 
            return [
                'success' => false,
                'message' => $errMsg,
            ];
        }
 
        $tempPath = storage_path('app/temp/' . uniqid() . '.mp4');
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
 
        try {
            // 1. Download video
            $downloadResponse = Http::sink($tempPath)->get($post->mediaItem->url);
            if ($downloadResponse->failed()) {
                $errMsg = 'Failed to download Reel video: HTTP ' . $downloadResponse->status();
                $this->logAction($post, 'publish_reel', false, [
                    'page_id' => $pageId,
                    'media_url' => $post->mediaItem->url,
                ], null, $errMsg);
 
                return [
                    'success' => false,
                    'message' => $errMsg,
                ];
            }
 
            // 2. Initialize Reel session
            $initUrl = "{$baseUrl}/{$pageId}/video_reels";
            $initResponse = Http::post($initUrl, [
                'upload_phase' => 'initialize',
                'access_token' => $token,
            ]);
 
            if ($initResponse->failed()) {
                $errMsg = 'Failed to initialize Reel upload: ' . $this->parseError($initResponse);
                $this->logAction($post, 'publish_reel_init', false, [
                    'page_id' => $pageId,
                ], null, $errMsg);
 
                return [
                    'success' => false,
                    'message' => $errMsg,
                ];
            }
 
            $initData = $initResponse->json();
            $videoId = $initData['video_id'] ?? null;
            $uploadUrl = $initData['upload_url'] ?? null;
 
            if (!$videoId || !$uploadUrl) {
                throw new RuntimeException('Reel session initialization did not return video_id or upload_url.');
            }
 
            // 3. Upload Reel video bytes
            $fileSize = filesize($tempPath);
            $uploadResponse = Http::withHeaders([
                'Authorization' => "OAuth {$token}",
                'offset' => 0,
                'file_size' => $fileSize,
            ])->withBody(file_get_contents($tempPath), 'application/octet-stream')
              ->post($uploadUrl);
 
            if ($uploadResponse->failed()) {
                $errMsg = 'Failed to upload Reel video bytes: ' . $this->parseError($uploadResponse);
                $this->logAction($post, 'publish_reel_upload', false, [
                    'page_id' => $pageId,
                    'video_id' => $videoId,
                ], null, $errMsg);
 
                return [
                    'success' => false,
                    'message' => $errMsg,
                ];
            }
 
            // 4. Publish Reel
            $publishResponse = Http::post($initUrl, [
                'upload_phase' => 'finish',
                'video_id' => $videoId,
                'video_state' => 'PUBLISHED',
                'description' => $post->caption,
                'access_token' => $token,
            ]);
 
            $publishData = $publishResponse->json();
 
            $this->logAction($post, 'publish_reel', $publishResponse->successful(), [
                'page_id' => $pageId,
                'video_id' => $videoId,
                'caption_preview' => substr($post->caption, 0, 100),
            ], $this->sanitizeResponse($publishData),
                $publishResponse->failed() ? $this->parseError($publishResponse) : null
            );
 
            if ($publishResponse->failed()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish Reel: ' . $this->parseError($publishResponse),
                ];
            }
 
            $fbPostId = $publishData['fb_id'] ?? $publishData['id'] ?? $videoId;
            $post->update([
                'status' => 'published',
                'facebook_post_id' => $fbPostId,
                'published_at' => now(),
                'error_message' => null,
            ]);
 
            return [
                'success' => true,
                'facebook_post_id' => $fbPostId,
                'message' => 'Reel published successfully.',
            ];
 
        } catch (\Exception $e) {
            $this->logAction($post, 'publish_reel', false, [
                'page_id' => $pageId,
            ], null, 'Reel publish error: ' . $e->getMessage());
 
            return [
                'success' => false,
                'message' => 'Reel publish failed: ' . $e->getMessage(),
            ];
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
 
    /**
     * Helper to log actions.
     */
    protected function logAction(
        PostQueue $post,
        string $action,
        bool $success,
        array $requestSummary = [],
        ?array $responseJson = null,
        ?string $errorMessage = null
    ): void {
        unset($requestSummary['access_token']);
        if ($responseJson) {
            unset($responseJson['access_token']);
        }
 
        \App\Models\PostPublishLog::create([
            'page_id' => $this->pageService->getPage()?->id,
            'post_queue_id' => $post->id,
            'mode' => $this->pageService->getPublishMode(),
            'provider' => 'facebook',
            'action' => $action,
            'status' => $success ? 'success' : 'failed',
            'request_summary' => $requestSummary ?: null,
            'response_json' => $responseJson,
            'error_message' => $errorMessage,
        ]);
    }
 
    protected function parseError($response): string
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
 
    /**
     * Helper to sanitize API response.
     */
    protected function sanitizeResponse(?array $data): ?array
    {
        if (!$data) return null;
        unset($data['access_token']);
        return $data;
    }
}
