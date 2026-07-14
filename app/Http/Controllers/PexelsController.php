<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\PostQueue;
use App\Services\CaptionService;
use App\Services\PexelsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PexelsController extends Controller
{
    public function index()
    {
        return Inertia::render('Pexels/Search', [
            'results' => null,
            'error' => null,
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
            'media_type' => 'required|in:photo,video,both',
        ]);

        try {
            $pexelsService = new PexelsService();
            $result = $pexelsService->search(
                $request->keyword,
                $request->media_type,
                10
            );

            if (isset($result['error'])) {
                return Inertia::render('Pexels/Search', [
                    'results' => null,
                    'error' => $result['error'],
                    'keyword' => $request->keyword,
                    'media_type' => $request->media_type,
                ]);
            }

            return Inertia::render('Pexels/Search', [
                'results' => $result['data'] ?? [],
                'error' => null,
                'keyword' => $request->keyword,
                'media_type' => $request->media_type,
            ]);
        } catch (\RuntimeException $e) {
            return Inertia::render('Pexels/Search', [
                'results' => null,
                'error' => $e->getMessage(),
                'keyword' => $request->keyword,
                'media_type' => $request->media_type,
            ]);
        }
    }

    public function createDraft(Request $request)
    {
        $request->validate([
            'media' => 'required|array',
            'media.pexels_id' => 'required|string',
            'media.type' => 'required|in:photo,video',
            'media.url' => 'required|string',
            'media.thumbnail_url' => 'required|string',
        ]);

        $mediaData = $request->input('media');

        // Save media item
        $mediaItem = MediaItem::updateOrCreate(
            ['pexels_id' => $mediaData['pexels_id']],
            [
                'type' => $mediaData['type'],
                'url' => $mediaData['url'],
                'thumbnail_url' => $mediaData['thumbnail_url'],
                'width' => $mediaData['width'] ?? null,
                'height' => $mediaData['height'] ?? null,
                'duration' => $mediaData['duration'] ?? null,
                'photographer' => $mediaData['photographer'] ?? null,
                'photographer_url' => $mediaData['photographer_url'] ?? null,
                'pexels_url' => $mediaData['pexels_url'] ?? null,
                'raw_json' => $mediaData['raw_json'] ?? null,
            ]
        );

        // Generate caption
        $captionService = new CaptionService();
        $topic = [
            'name' => 'General',
            'keyword' => $mediaData['photographer'] ?? 'beautiful',
        ];
        $caption = $captionService->generate($topic, $mediaData, 'english');

        // Create draft post
        PostQueue::create([
            'media_item_id' => $mediaItem->id,
            'caption' => $caption,
            'status' => 'draft',
            'scheduled_at' => now()->addHour(),
        ]);

        return redirect()->route('queue.index')
            ->with('success', 'Draft post created successfully!');
    }

    /**
     * Run AI analysis on stock media item.
     */
    public function analyzeMedia(Request $request)
    {
        $request->validate([
            'media' => 'required|array',
            'keyword' => 'nullable|string',
        ]);

        $mediaData = $request->input('media');
        $keyword = $request->input('keyword', 'beautiful');

        $gemini = new \App\Services\AI\GeminiService();
        $analysis = $gemini->analyzeMedia($mediaData, $keyword);

        return response()->json($analysis);
    }
}
