<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Topic;
use App\Services\AI\GeminiService;
use Illuminate\Http\Request;

class AICaptionController extends Controller
{
    /**
     * Generate 3 caption variants using Gemini AI.
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'topic_id' => 'required|exists:topics,id',
            'media_item_id' => 'nullable|exists:media_items,id',
            'language' => 'nullable|string',
            'preset' => 'nullable|string',
        ]);

        $topic = Topic::findOrFail($validated['topic_id']);
        $media = $validated['media_item_id'] 
            ? MediaItem::findOrFail($validated['media_item_id'])->toArray() 
            : ['type' => 'text', 'photographer' => 'N/A'];

        $language = $validated['language'] ?? 'english';
        $preset = $validated['preset'] ?? 'facebook_engagement';

        $gemini = new GeminiService();
        if (!$gemini->isEnabled()) {
            return response()->json([
                'error' => 'Gemini AI is disabled. Please enable it in Settings.',
                'variants' => [
                    "Variant 1 (Local template): Beautiful shot of " . ($topic->name ?? 'nature'),
                    "Variant 2 (Local template): Loving this " . ($topic->keyword ?? 'nature') . " vibe!",
                    "Variant 3 (Local template): Stunning view captured by " . ($media['photographer'] ?? 'photographer')
                ]
            ]);
        }

        $result = $gemini->generateCaptionVariants($topic->toArray(), $media, $language, $preset);

        return response()->json($result);
    }
}
