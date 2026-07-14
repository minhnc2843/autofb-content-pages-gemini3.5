<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class PexelsService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = Setting::getValue('PEXELS_API_KEY');
    }

    /**
     * Search for photos on Pexels.
     */
    public function searchPhotos(string $keyword, int $perPage = 10): array
    {
        $this->ensureApiKey();

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get('https://api.pexels.com/v1/search', [
                'query' => $keyword,
                'per_page' => $perPage,
            ]);

            if ($response->status() === 401) {
                return ['error' => 'Invalid Pexels API key. Please check your API key in Settings.', 'status' => 401];
            }

            if ($response->status() === 429) {
                return ['error' => 'Pexels API rate limit exceeded. Please try again later.', 'status' => 429];
            }

            if ($response->failed()) {
                return ['error' => 'Pexels API error: ' . $response->status(), 'status' => $response->status()];
            }

            $data = $response->json();
            $photos = collect($data['photos'] ?? [])->map(function ($photo) {
                return [
                    'pexels_id' => (string) $photo['id'],
                    'type' => 'photo',
                    'url' => $photo['src']['original'] ?? $photo['src']['large'],
                    'thumbnail_url' => $photo['src']['medium'] ?? $photo['src']['small'],
                    'width' => $photo['width'] ?? null,
                    'height' => $photo['height'] ?? null,
                    'duration' => null,
                    'photographer' => $photo['photographer'] ?? null,
                    'photographer_url' => $photo['photographer_url'] ?? null,
                    'pexels_url' => $photo['url'] ?? null,
                    'raw_json' => $photo,
                ];
            })->toArray();

            return ['data' => $photos, 'total' => $data['total_results'] ?? 0];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['error' => 'Network error: Could not connect to Pexels API. Please check your internet connection.', 'status' => 0];
        }
    }

    /**
     * Search for videos on Pexels.
     */
    public function searchVideos(string $keyword, int $perPage = 10): array
    {
        $this->ensureApiKey();

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get('https://api.pexels.com/videos/search', [
                'query' => $keyword,
                'per_page' => $perPage,
            ]);

            if ($response->status() === 401) {
                return ['error' => 'Invalid Pexels API key. Please check your API key in Settings.', 'status' => 401];
            }

            if ($response->status() === 429) {
                return ['error' => 'Pexels API rate limit exceeded. Please try again later.', 'status' => 429];
            }

            if ($response->failed()) {
                return ['error' => 'Pexels API error: ' . $response->status(), 'status' => $response->status()];
            }

            $data = $response->json();
            $videos = collect($data['videos'] ?? [])->map(function ($video) {
                // Find MP4 files that are <= 1080p
                $videoFile = collect($video['video_files'] ?? [])
                    ->filter(function ($file) {
                        return str_contains(strtolower($file['file_type'] ?? ''), 'video/mp4') || 
                               str_contains(strtolower($file['link'] ?? ''), '.mp4');
                    })
                    ->filter(function ($file) {
                        $w = $file['width'] ?? 0;
                        $h = $file['height'] ?? 0;
                        return $w <= 1920 && $h <= 1080;
                    })
                    ->sortByDesc(function ($file) {
                        return $file['width'] ?? 0;
                    })
                    ->first();

                if (!$videoFile) {
                    // Fallback 1: get any mp4 file
                    $videoFile = collect($video['video_files'] ?? [])
                        ->first(function ($file) {
                            return str_contains(strtolower($file['file_type'] ?? ''), 'video/mp4') || 
                                   str_contains(strtolower($file['link'] ?? ''), '.mp4');
                        });
                }

                if (!$videoFile) {
                    // Fallback 2: first video file available
                    $videoFile = $video['video_files'][0] ?? null;
                }

                return [
                    'pexels_id' => (string) $video['id'],
                    'type' => 'video',
                    'url' => $videoFile['link'] ?? '',
                    'thumbnail_url' => $video['image'] ?? '',
                    'width' => $video['width'] ?? null,
                    'height' => $video['height'] ?? null,
                    'duration' => $video['duration'] ?? null,
                    'photographer' => $video['user']['name'] ?? null,
                    'photographer_url' => $video['user']['url'] ?? null,
                    'pexels_url' => $video['url'] ?? null,
                    'raw_json' => $video,
                ];
            })->toArray();

            return ['data' => $videos, 'total' => $data['total_results'] ?? 0];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['error' => 'Network error: Could not connect to Pexels API. Please check your internet connection.', 'status' => 0];
        }
    }

    /**
     * Search for both photos and videos.
     */
    public function search(string $keyword, string $type = 'both', int $perPage = 10): array
    {
        if ($type === 'photo') {
            return $this->searchPhotos($keyword, $perPage);
        }

        if ($type === 'video') {
            return $this->searchVideos($keyword, $perPage);
        }

        // both
        $halfPage = max(1, intval($perPage / 2));
        $photos = $this->searchPhotos($keyword, $halfPage);
        $videos = $this->searchVideos($keyword, $halfPage);

        if (isset($photos['error'])) {
            return $photos;
        }
        if (isset($videos['error'])) {
            return $videos;
        }

        return [
            'data' => array_merge($photos['data'] ?? [], $videos['data'] ?? []),
            'total' => ($photos['total'] ?? 0) + ($videos['total'] ?? 0),
        ];
    }

    /**
     * Ensure API key is configured.
     */
    protected function ensureApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException(
                'Pexels API key is not configured. Please set PEXELS_API_KEY in your .env file or in Settings.'
            );
        }
    }
}
