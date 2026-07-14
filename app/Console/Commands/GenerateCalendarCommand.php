<?php

namespace App\Console\Commands;

use App\Services\ContentCalendarService;
use Illuminate\Console\Command;

class GenerateCalendarCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:generate-calendar {--days=7 : The number of days to schedule} {--posts-per-day=3 : Number of posts per day} {--start-date= : Start date (YYYY-MM-DD)} {--media-type=both : Media type (photo, video, both)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generate a draft content schedule calendar avoiding duplicates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int)$this->option('days');
        $postsPerDay = (int)$this->option('posts-per-day');
        $startDate = $this->option('start-date');
        $mediaType = $this->option('media-type') ?: 'both';

        $this->info("Starting auto-generation of content schedule for {$days} days ({$postsPerDay} posts/day)...");

        $service = new ContentCalendarService();
        $summary = $service->generateScheduleForDays($days, [
            'posts_per_day' => $postsPerDay,
            'start_date' => $startDate,
            'media_type' => $mediaType,
        ]);

        if (!empty($summary['errors'])) {
            foreach ($summary['errors'] as $err) {
                $this->error("  Issue: {$err}");
            }
        }

        $this->info("Successfully generated {$summary['created']} draft posts in the calendar queue! (Skipped: {$summary['skipped']}, Failed: {$summary['failed']})");

        return Command::SUCCESS;
    }
}
