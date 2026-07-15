<?php

namespace App\Console\Commands;

use App\Services\DuePostPublisherService;
use App\Models\Setting;
use Illuminate\Console\Command;

class PublishDuePostsCommand extends Command
{
    protected $signature = 'posts:publish-due {--page= : Filter by page ID}';
    protected $description = 'Publish due approved posts via Facebook Pages API or fake mode';

    public function handle(): int
    {
        $service = new DuePostPublisherService();
        $pageId = $this->option('page');
        
        $this->info("Checking for due posts to publish...");

        $result = $service->publishDuePosts(false, $pageId ? intval($pageId) : null);

        $found = $result['found'];
        $published = $result['published'];
        $failed = $result['failed'];
        $mode = $result['mode'];

        // PHẦN 4 — LƯU LAST SCHEDULER RUN / HEARTBEAT
        Setting::setValue('PUBLISH_DUE_LAST_RUN_AT', now()->toDateTimeString());
        Setting::setValue('PUBLISH_DUE_LAST_FOUND', (string) $found);
        Setting::setValue('PUBLISH_DUE_LAST_PUBLISHED', (string) $published);
        Setting::setValue('PUBLISH_DUE_LAST_FAILED', (string) $failed);

        if ($found === 0) {
            $this->info('No due posts found.');
            return self::SUCCESS;
        }

        $this->info("Found {$found} due post(s).");

        foreach ($result['posts'] as $p) {
            $statusStr = $p['success'] ? 'published' : 'failed';
            $icon = $p['success'] ? '✅' : '❌';
            $this->line("  {$icon} Post #{$p['id']} [Scheduled: {$p['scheduled_at']}] -> {$statusStr}. Message: {$p['message']}");
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Total found: {$found}");
        $this->info("Published: {$published}");
        $this->info("Failed: {$failed}");
        $this->info("Mode: {$mode}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
