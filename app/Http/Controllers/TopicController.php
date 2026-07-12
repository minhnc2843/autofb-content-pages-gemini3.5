<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Topic::orderBy('created_at', 'desc')->get();

        return Inertia::render('Topics/Index', [
            'topics' => $topics,
        ]);
    }

    public function create()
    {
        return Inertia::render('Topics/Form', [
            'topic' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'keyword' => 'required|string|max:255',
            'language' => 'required|in:english,thai,lao,khmer,vietnamese',
            'media_type' => 'required|in:photo,video,both',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Topic::create($validated);

        return redirect()->route('topics.index')
            ->with('success', 'Topic created successfully.');
    }

    public function edit(Topic $topic)
    {
        return Inertia::render('Topics/Form', [
            'topic' => $topic,
        ]);
    }

    public function update(Request $request, Topic $topic)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'keyword' => 'required|string|max:255',
            'language' => 'required|in:english,thai,lao,khmer,vietnamese',
            'media_type' => 'required|in:photo,video,both',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $topic->update($validated);

        return redirect()->route('topics.index')
            ->with('success', 'Topic updated successfully.');
    }

    public function destroy(Topic $topic)
    {
        $topic->delete();

        return redirect()->route('topics.index')
            ->with('success', 'Topic deleted successfully.');
    }

    public function toggleActive(Topic $topic)
    {
        $topic->update(['is_active' => !$topic->is_active]);

        return redirect()->route('topics.index')
            ->with('success', 'Topic status updated.');
    }
}
