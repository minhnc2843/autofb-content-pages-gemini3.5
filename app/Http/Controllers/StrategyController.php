<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\AiAnalysis;
use App\Services\AI\GeminiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StrategyController extends Controller
{
    /**
     * Display weekly content strategy engine.
     */
    public function index()
    {
        $topics = Topic::where('is_active', true)->get();
        if ($topics->isEmpty()) {
            $topics = Topic::all();
        }

        $gemini = new GeminiService();
        $geminiEnabled = $gemini->isEnabled();

        // Retrieve the latest strategy from the database
        $latestAnalysis = AiAnalysis::where('target_type', 'strategy')
            ->orderBy('created_at', 'desc')
            ->first();

        $strategy = null;
        if ($latestAnalysis) {
            $strategy = $latestAnalysis->result_json;
        }

        return Inertia::render('Strategy/Index', [
            'strategy' => $strategy,
            'topics' => $topics->map(fn($t) => ['id' => $t->id, 'name' => $t->name]),
            'geminiEnabled' => $geminiEnabled,
        ]);
    }

    /**
     * Generate content strategy using Gemini.
     */
    public function generate(Request $request)
    {
        $gemini = new GeminiService();
        if (!$gemini->isEnabled()) {
            return redirect()->route('strategy.index')
                ->with('error', 'Gemini AI is currently disabled. Please enable it in Settings.');
        }

        $topics = Topic::where('is_active', true)->get();
        if ($topics->isEmpty()) {
            $topics = Topic::all();
        }

        if ($topics->isEmpty()) {
            return redirect()->route('strategy.index')
                ->with('error', 'No topics available to generate strategy.');
        }

        $strategy = $gemini->generateStrategy($topics->toArray());

        if (isset($strategy['error'])) {
            return redirect()->route('strategy.index')
                ->with('error', 'Failed to generate strategy: ' . $strategy['error']);
        }

        // Save to database
        AiAnalysis::create([
            'target_type' => 'strategy',
            'target_id' => 1,
            'provider' => 'gemini',
            'result_json' => $strategy,
        ]);

        return redirect()->route('strategy.index')
            ->with('success', 'New weekly strategy generated successfully!');
    }
}
