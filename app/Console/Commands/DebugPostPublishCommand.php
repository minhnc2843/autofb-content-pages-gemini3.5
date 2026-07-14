<?php

namespace App\Console\Commands;

use App\Models\PostQueue;
use App\Services\FacebookPageService;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugPostPublishCommand extends Command
{
    protected $signature = 'posts:debug-publish {postId} {--validate-config}';
    protected $description = 'Perform diagnostic checks on a specific post queue item and optionally validate Facebook configurations';

    public function handle(): int
    {
        $postId = $this->argument('postId');
        $post = PostQueue::with(['mediaItem', 'publishLogs'])->find($postId);

        if (!$post) {
            $this->error("Post #{$postId} not found in the queue.");
            return self::FAILURE;
        }

        $service = new FacebookPageService();
        $publishMode = $service->getPublishMode();

        $this->info("=== Diagnosing Post #{$post->id} ===");
        $this->line("App Timezone: " . config('app.timezone'));
        $this->line("Current App Time: " . now()->toDateTimeString());
        $this->line("Status: " . $post->status);
        $this->line("Scheduled At: " . ($post->scheduled_at ? $post->scheduled_at->toDateTimeString() : 'N/A'));

        $isDue = $post->status === 'approved' && $post->scheduled_at && $post->scheduled_at->isPast();
        $this->line("Due for Auto-Publishing: " . ($isDue ? 'YES' : 'NO'));
        $this->line("Total Due Posts in Queue: " . PostQueue::due()->count());

        if ($post->status !== 'approved') {
            $this->warn("⚠️  Warning: Only 'approved' posts can auto-publish. Current status is '{$post->status}'.");
        }
        if ($post->status === 'approved' && $post->scheduled_at && $post->scheduled_at->isFuture()) {
            $this->warn("⚠️  Warning: This post is not due yet. Check timezone.");
        }

        $mediaType = $post->mediaItem?->type ?? 'text';
        $this->line("Media Type: " . $mediaType);

        $mediaUrl = $post->mediaItem?->url ?? 'N/A';
        $this->line("Media Source URL: " . $mediaUrl);

        if ($mediaType !== 'text') {
            if (!$post->mediaItem?->url) {
                $this->warn("⚠️  Warning: Media item is missing a source URL.");
            } else {
                if ($mediaType === 'photo' && str_contains($post->mediaItem->url, 'images.pexels.com') && !str_contains($post->mediaItem->url, 'auto=compress')) {
                    $this->warn("⚠️  Warning: Photo URL is not optimized for Facebook publish.");
                    $base = strtok($post->mediaItem->url, '?');
                    $suggestedUrl = $base . '?auto=compress&cs=tinysrgb&w=1600';
                    $this->line("  Suggested optimized URL: " . $suggestedUrl);
                }

                // Test accessibility of media url using HTTP HEAD
                try {
                    $response = Http::timeout(5)->head($post->mediaItem->url);
                    if ($response->successful()) {
                        $this->info("  ✅ Media source URL is accessible (HTTP status {$response->status()}).");
                    } else {
                        $this->warn("  ⚠️  Warning: Media source URL returned non-success HTTP status {$response->status()}.");
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Error: Media source URL is not accessible. Exception: " . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info("=== Facebook Settings & Configuration ===");
        $this->line("FACEBOOK_PUBLISH_MODE: " . $publishMode);

        $publishAsReel = Setting::getValue('FACEBOOK_PUBLISH_AS_REEL', env('FACEBOOK_PUBLISH_AS_REEL', 'false'));
        $this->line("FACEBOOK_PUBLISH_AS_REEL: " . $publishAsReel);

        $videoUploadMode = Setting::getValue('FACEBOOK_VIDEO_UPLOAD_MODE', env('FACEBOOK_VIDEO_UPLOAD_MODE', 'remote_url'));
        $this->line("FACEBOOK_VIDEO_UPLOAD_MODE: " . $videoUploadMode);

        $pageId = Setting::getValue('FACEBOOK_PAGE_ID', env('FACEBOOK_PAGE_ID'));
        $hasPageId = !empty($pageId);
        $this->line("Page ID configured: " . ($hasPageId ? 'YES' : 'NO'));
        if (!$hasPageId) {
            $this->error("❌ Error: FACEBOOK_PAGE_ID is not configured.");
        }

        $token = Setting::getValue('FACEBOOK_PAGE_ACCESS_TOKEN', env('FACEBOOK_PAGE_ACCESS_TOKEN'));
        $hasToken = !empty($token);
        $this->line("Page Access Token configured: " . ($hasToken ? 'YES' : 'NO'));
        if (!$hasToken) {
            $this->error("❌ Error: FACEBOOK_PAGE_ACCESS_TOKEN is not configured.");
        }

        $this->newLine();
        $this->info("=== Recent Errors & Logs ===");
        $this->line("Latest Error Message: " . ($post->error_message ?: 'None'));

        $latestLog = $post->publishLogs()->latest()->first();
        if ($latestLog) {
            $this->line("Latest Publish Log: [#{$latestLog->id}] status: {$latestLog->status}, action: {$latestLog->action}, created: {$latestLog->created_at}");
            if ($latestLog->error_message) {
                $this->line("  Log Error: " . $latestLog->error_message);
            }
        } else {
            $this->line("Latest Publish Log: None");
        }

        if ($this->option('validate-config')) {
            $this->newLine();
            $this->info("=== Validating Facebook Configuration ===");
            try {
                $validResult = $service->validateConfig();
                if ($validResult['success']) {
                    $this->info("  ✅ Configuration is VALID.");
                    $this->info("  Page Name: " . ($validResult['page_name'] ?? 'N/A'));
                } else {
                    $this->error("  ❌ Configuration is INVALID: " . $validResult['message']);
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Validation failed with exception: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
