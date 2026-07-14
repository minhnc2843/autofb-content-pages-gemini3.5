<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use Illuminate\Console\Command;

class OptimizePexelsUrlsCommand extends Command
{
    protected $signature = 'media:optimize-pexels-urls';
    protected $description = 'Optimize existing Pexels photo and thumbnail URLs in database';

    public function handle(): int
    {
        $this->info('Scanning database for unoptimized Pexels photos...');

        $mediaItems = MediaItem::where('type', 'photo')
            ->where('url', 'like', '%images.pexels.com%')
            ->get();

        if ($mediaItems->isEmpty()) {
            $this->info('No unoptimized Pexels photos found.');
            return self::SUCCESS;
        }

        $this->info("Found {$mediaItems->count()} Pexels photo(s) to optimize.");

        $updatedCount = 0;

        foreach ($mediaItems as $item) {
            $oldUrl = $item->url;
            $oldThumb = $item->thumbnail_url;

            $newUrl = $this->optimizeUrl($oldUrl, 1600);
            $newThumb = $this->optimizeUrl($oldThumb, 600);

            if ($oldUrl !== $newUrl || $oldThumb !== $newThumb) {
                $item->update([
                    'url' => $newUrl,
                    'thumbnail_url' => $newThumb,
                ]);
                $updatedCount++;
            }
        }

        $this->info("Successfully optimized {$updatedCount} Pexels photo(s).");

        return self::SUCCESS;
    }

    protected function optimizeUrl(?string $url, int $width): ?string
    {
        if (!$url) return null;

        if (!str_contains($url, 'images.pexels.com')) {
            return $url;
        }

        $base = strtok($url, '?');
        return $base . '?auto=compress&cs=tinysrgb&w=' . $width;
    }
}
