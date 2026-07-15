<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageProfile;
use App\Models\PageTopic;
use App\Services\FacebookPageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::with('profile')->get()->map(function ($page) {
            // Mask access token
            $token = $page->access_token;
            $maskedToken = '';
            if (!empty($token)) {
                $maskedToken = str_repeat('•', min(strlen($token), 8)) . substr($token, -4);
            }
            $page->access_token_masked = $maskedToken;
            unset($page->access_token);
            return $page;
        });

        return Inertia::render('Pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function create()
    {
        return Inertia::render('Pages/Form', [
            'presets' => $this->getPresets(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|string',
            'facebook_page_id' => 'nullable|string|max:255',
            'facebook_page_name' => 'nullable|string|max:255',
            'facebook_page_link' => 'nullable|string|max:1000',
            'access_token' => 'nullable|string|max:2000',
            'publish_mode' => 'required|in:fake,real',
            'is_active' => 'required|boolean',
            'timezone' => 'required|string',
            'language' => 'required|string',
            'niche' => 'nullable|string',
            'content_tone' => 'nullable|string',
            'notes' => 'nullable|string',

            // Profile fields
            'description' => 'nullable|string',
            'audience' => 'nullable|string',
            'content_goals' => 'nullable|string',
            'avoid_topics' => 'nullable|string',
            'preferred_media_types' => 'nullable|array',
            'content_mix' => 'nullable|array',
            'posting_slots' => 'nullable|array',
            'approval_mode' => 'required|in:manual,semi_auto,full_auto',
            'auto_approve_min_score' => 'nullable|integer|min:0|max:100',
            'max_posts_per_day' => 'required|integer|min:1',
            'hashtag_policy' => 'nullable|string',
            'language_policy' => 'nullable|string',

            // Preset selection
            'preset' => 'nullable|string',
        ]);

        $token = $request->input('access_token');
        if ($token && preg_match('/^•+/', $token)) {
            $token = null; // Do not save masked value
        }

        $pageData = $request->only([
            'name', 'platform', 'facebook_page_id', 'facebook_page_name',
            'facebook_page_link', 'publish_mode', 'is_active', 'timezone',
            'language', 'niche', 'content_tone', 'notes'
        ]);

        if ($token) {
            $pageData['access_token'] = $token;
        }

        // Apply preset if selected and not Custom
        $presetKey = $request->input('preset');
        $presets = $this->getPresets();

        if ($presetKey && $presetKey !== 'custom' && isset($presets[$presetKey])) {
            $preset = $presets[$presetKey];
            $pageData['niche'] = $preset['niche'];
            $pageData['content_tone'] = $preset['content_tone'];
            $pageData['language'] = $preset['language'];
        }

        $page = Page::create($pageData);

        // Profile saving
        $profileData = $request->only([
            'description', 'audience', 'content_goals', 'avoid_topics',
            'preferred_media_types', 'content_mix', 'posting_slots',
            'approval_mode', 'auto_approve_min_score', 'max_posts_per_day',
            'hashtag_policy', 'language_policy'
        ]);

        if ($presetKey && $presetKey !== 'custom' && isset($presets[$presetKey])) {
            $preset = $presets[$presetKey];
            $profileData = array_merge($profileData, $preset['profile']);
        }

        $page->profile()->create($profileData);

        // Topics saving if preset
        if ($presetKey && $presetKey !== 'custom' && isset($presets[$presetKey])) {
            $preset = $presets[$presetKey];
            foreach ($preset['topics'] as $topicName) {
                $page->topics()->create([
                    'name' => $topicName,
                    'keyword' => $topicName,
                    'is_active' => true,
                ]);
            }
        }

        return redirect()->route('pages.index')->with('success', 'Page created successfully.');
    }

    public function edit(Page $page)
    {
        $page->load('profile', 'topics');

        // Mask token
        $token = $page->access_token;
        $maskedToken = '';
        if (!empty($token)) {
            $maskedToken = str_repeat('•', min(strlen($token), 8)) . substr($token, -4);
        }
        $page->access_token_masked = $maskedToken;
        unset($page->access_token);

        return Inertia::render('Pages/Form', [
            'page' => $page,
            'presets' => $this->getPresets(),
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|string',
            'facebook_page_id' => 'nullable|string|max:255',
            'facebook_page_name' => 'nullable|string|max:255',
            'facebook_page_link' => 'nullable|string|max:1000',
            'access_token' => 'nullable|string|max:2000',
            'publish_mode' => 'required|in:fake,real',
            'is_active' => 'required|boolean',
            'timezone' => 'required|string',
            'language' => 'required|string',
            'niche' => 'nullable|string',
            'content_tone' => 'nullable|string',
            'notes' => 'nullable|string',

            // Profile fields
            'description' => 'nullable|string',
            'audience' => 'nullable|string',
            'content_goals' => 'nullable|string',
            'avoid_topics' => 'nullable|string',
            'preferred_media_types' => 'nullable|array',
            'content_mix' => 'nullable|array',
            'posting_slots' => 'nullable|array',
            'approval_mode' => 'required|in:manual,semi_auto,full_auto',
            'auto_approve_min_score' => 'nullable|integer|min:0|max:100',
            'max_posts_per_day' => 'required|integer|min:1',
            'hashtag_policy' => 'nullable|string',
            'language_policy' => 'nullable|string',
        ]);

        $token = $request->input('access_token');
        $pageData = $request->only([
            'name', 'platform', 'facebook_page_id', 'facebook_page_name',
            'facebook_page_link', 'publish_mode', 'is_active', 'timezone',
            'language', 'niche', 'content_tone', 'notes'
        ]);

        if ($token && !preg_match('/^•+/', $token)) {
            $pageData['access_token'] = $token;
        }

        $page->update($pageData);

        $profileData = $request->only([
            'description', 'audience', 'content_goals', 'avoid_topics',
            'preferred_media_types', 'content_mix', 'posting_slots',
            'approval_mode', 'auto_approve_min_score', 'max_posts_per_day',
            'hashtag_policy', 'language_policy'
        ]);

        $page->profile()->updateOrCreate(['page_id' => $page->id], $profileData);

        return redirect()->route('pages.index')->with('success', 'Page updated successfully.');
    }

    public function toggleActive(Page $page)
    {
        $page->update(['is_active' => !$page->is_active]);
        return redirect()->back()->with('success', 'Page status toggled.');
    }

    public function setDefault(Page $page)
    {
        $currentDefault = Page::where('slug', 'default-facebook-page')->first();
        if ($currentDefault && $currentDefault->id !== $page->id) {
            $currentDefault->slug = 'page-' . $currentDefault->id . '-' . Str::slug($currentDefault->name);
            $currentDefault->save();
        }

        $page->slug = 'default-facebook-page';
        $page->save();

        return redirect()->back()->with('success', 'Page set as default.');
    }

    public function validateFacebook(Page $page)
    {
        $service = new FacebookPageService($page);
        $result = $service->validateConfig();

        if ($result['success']) {
            $page->update([
                'facebook_page_name' => $result['page_name'] ?? $page->facebook_page_name,
                'facebook_page_link' => $result['page_link'] ?? $page->facebook_page_link,
            ]);
            return redirect()->back()->with('success', "✅ Facebook configuration is valid for page {$result['page_name']} ({$result['page_id']})");
        } else {
            return redirect()->back()->with('error', "❌ Validation failed: {$result['message']}");
        }
    }

    protected function getPresets(): array
    {
        return [
            'nature_healing' => [
                'name' => 'Nature Healing',
                'niche' => 'Nature & Relaxation',
                'content_tone' => 'calm, peaceful, healing',
                'language' => 'english',
                'profile' => [
                    'description' => 'Calm, peaceful, and healing natural environments.',
                    'audience' => 'People seeking relaxation, meditation, and peace.',
                    'content_goals' => 'Publish beautiful nature photos and videos to help people de-stress.',
                    'avoid_topics' => 'Superstition, commercial calls, politics.',
                    'preferred_media_types' => ['photo', 'video'],
                    'content_mix' => ['photo' => 30, 'video' => 70, 'text' => 0],
                    'posting_slots' => ['07:30', '12:30', '20:30'],
                    'approval_mode' => 'manual',
                    'auto_approve_min_score' => 85,
                    'max_posts_per_day' => 3,
                    'hashtag_policy' => 'short, emotional, no spam hashtags',
                    'language_policy' => 'english',
                ],
                'topics' => ['forest', 'rain', 'ocean', 'sunset', 'morning calm', 'sleep relaxation']
            ],
            'buddhist_teaching' => [
                'name' => 'Buddhist Teaching',
                'niche' => 'Buddhism & Philosophy',
                'content_tone' => 'respectful, peaceful, reflective',
                'language' => 'english',
                'profile' => [
                    'description' => 'Buddhist teachings and inspirational texts.',
                    'audience' => 'Practitioners, philosophical seekers, and mindful individuals.',
                    'content_goals' => 'Provide reflective thoughts, teachings, and reminders on inner peace.',
                    'avoid_topics' => 'Superstition, luck/money promises, engagement bait.',
                    'preferred_media_types' => ['photo', 'video', 'text'],
                    'content_mix' => ['photo' => 60, 'video' => 30, 'text' => 10],
                    'posting_slots' => ['06:00', '12:30', '20:00'],
                    'approval_mode' => 'manual',
                    'auto_approve_min_score' => 85,
                    'max_posts_per_day' => 3,
                    'hashtag_policy' => 'short, reflective hashtags',
                    'language_policy' => 'english',
                ],
                'topics' => ['compassion', 'karma', 'filial piety', 'letting go', 'inner peace']
            ],
            'animals' => [
                'name' => 'Animals',
                'niche' => 'Cute & Funny Animals',
                'content_tone' => 'cute, fun, heartwarming',
                'language' => 'english',
                'profile' => [
                    'description' => 'Cuteness overload with funny animal reels and sweet pet images.',
                    'audience' => 'Animal enthusiasts, pet owners, general public.',
                    'content_goals' => 'Amuse and delight people with animal videos.',
                    'avoid_topics' => 'Animal cruelty, political topics, heavy text.',
                    'preferred_media_types' => ['photo', 'video'],
                    'content_mix' => ['photo' => 20, 'video' => 80, 'text' => 0],
                    'posting_slots' => ['09:00', '15:00', '21:00'],
                    'approval_mode' => 'manual',
                    'auto_approve_min_score' => 85,
                    'max_posts_per_day' => 3,
                    'hashtag_policy' => 'short hook, question, simple CTA',
                    'language_policy' => 'english',
                ],
                'topics' => ['funny animals', 'cute pets', 'animal friendship', 'wildlife calm']
            ]
        ];
    }
}
