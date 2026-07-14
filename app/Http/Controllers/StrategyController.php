<?php

namespace App\Http\Controllers;

use App\Models\Topic;
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
        $strategy = $gemini->generateStrategy($topics->toArray());

        return Inertia::render('Strategy/Index', [
            'strategy' => $strategy,
            'topics' => $topics->map(fn($t) => ['id' => $t->id, 'name' => $t->name]),
        ]);
    }
}
