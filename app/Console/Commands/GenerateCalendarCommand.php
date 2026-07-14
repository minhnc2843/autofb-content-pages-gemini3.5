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
    protected $signature = 'posts:generate-calendar {--days=7 : The number of days to schedule} {--posts-per-day=3 : Number of posts per day (1, 2, or 3)}';

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

        $this->info("Starting auto-generation of content schedule for {$days} days ({$postsPerDay} posts/day)...");

        $service = new ContentCalendarService();
        $count = $service->generateSchedule($days, $postsPerDay);

        $this->info("Successfully generated {$count} draft posts in the calendar queue!");

        return Command::SUCCESS;
    }
}
