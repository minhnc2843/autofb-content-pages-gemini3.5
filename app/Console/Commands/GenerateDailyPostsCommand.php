<?php

namespace App\Console\Commands;

use App\Services\ContentCalendarService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateDailyPostsCommand extends Command
{
    protected $signature = 'posts:generate-daily';
    protected $description = 'Generate daily draft posts for active topics';

    public function handle(): int
    {
        $this->info('Starting daily post generation...');

        $service = new ContentCalendarService();
        $summary = $service->generateScheduleForDays(1, [
            'posts_per_day' => 3,
            'start_date' => Carbon::today()->toDateString(),
        ]);

        if (!empty($summary['errors'])) {
            foreach ($summary['errors'] as $err) {
                $this->error("  Issue: {$err}");
            }
        }

        $this->info("Daily post generation complete. Created {$summary['created']} draft posts. (Skipped: {$summary['skipped']}, Failed: {$summary['failed']})");
        return self::SUCCESS;
    }
}
