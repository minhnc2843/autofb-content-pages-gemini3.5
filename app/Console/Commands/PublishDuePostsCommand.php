<?php

namespace App\Console\Commands;

use App\Models\PostQueue;
use App\Services\FacebookPageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishDuePostsCommand extends Command
{
    protected $signature = 'posts:publish-due';
    protected $description = 'Publish due approved posts via Facebook Pages API or fake mode';

    public function handle(): int
    {
        $service = new FacebookPageService();
        $mode = $service->getPublishMode();

        $this->info("Checking for due posts to publish... (mode: {$mode})");

        $duePosts = PostQueue::due()->with('mediaItem')->get();

        if ($duePosts->isEmpty()) {
            $this->info('No due posts found.');
            return self::SUCCESS;
        }

        $this->info("Found {$duePosts->count()} due post(s).");

        $published = 0;
        $failed = 0;

        foreach ($duePosts as $post) {
            $type = $post->mediaItem?->type ?? 'text';
            $this->info("Processing post #{$post->id} [Type: {$type}] (scheduled: {$post->scheduled_at})");

            try {
                $result = $service->publishPost($post);

                if ($result['success']) {
                    $published++;
                    $statusLabel = $result['mode'] ?? $mode;
                    $this->info("  ✅ Post #{$post->id} published ({$statusLabel}). {$result['message']}");
                } else {
                    $failed++;
                    $this->error("  ❌ Post #{$post->id} failed: {$result['message']}");
                }
            } catch (\Exception $e) {
                $failed++;
                $post->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("  ❌ Post #{$post->id} exception: {$e->getMessage()}");

                Log::error("Post #{$post->id} publish exception", [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $statsByType = $duePosts->groupBy(function($post) {
            return $post->mediaItem?->type ?? 'text';
        })->map->count();

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Total found: {$duePosts->count()}");
        $this->info("By Type: Text: " . ($statsByType['text'] ?? 0) . ", Photo: " . ($statsByType['photo'] ?? 0) . ", Video: " . ($statsByType['video'] ?? 0));
        $this->info("Published: {$published}");
        $this->info("Failed: {$failed}");
        $this->info("Mode: {$mode}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
