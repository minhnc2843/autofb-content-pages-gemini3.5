<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\FacebookPageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settingKeys = [
            'PEXELS_API_KEY',
            'FACEBOOK_PAGE_ID',
            'FACEBOOK_PAGE_ACCESS_TOKEN',
            'GEMINI_API_KEY',
            'META_GRAPH_VERSION',
            'FACEBOOK_PUBLISH_MODE',
        ];

        $settings = [];
        foreach ($settingKeys as $key) {
            $setting = Setting::where('key', $key)->first();
            $value = $setting?->value ?? '';

            // Mask secret fields — never send full token to frontend after save
            if ($setting?->is_secret && !empty($value)) {
                $settings[strtolower($key)] = str_repeat('•', min(strlen($value), 8)) . substr($value, -4);
            } else {
                $settings[strtolower($key)] = $value;
            }
        }

        // Also check env values for display
        $settings['meta_graph_version'] = $settings['meta_graph_version'] ?: env('META_GRAPH_VERSION', 'v25.0');
        $settings['facebook_publish_mode'] = $settings['facebook_publish_mode'] ?: env('FACEBOOK_PUBLISH_MODE', 'fake');

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'pexels_api_key' => 'nullable|string|max:500',
            'facebook_page_id' => 'nullable|string|max:500',
            'facebook_page_access_token' => 'nullable|string|max:1000',
            'gemini_api_key' => 'nullable|string|max:500',
            'meta_graph_version' => 'nullable|string|max:20',
            'facebook_publish_mode' => 'nullable|in:fake,real',
        ]);

        $secretFields = ['pexels_api_key', 'facebook_page_access_token', 'gemini_api_key'];

        $fields = [
            'PEXELS_API_KEY' => $request->input('pexels_api_key'),
            'FACEBOOK_PAGE_ID' => $request->input('facebook_page_id'),
            'FACEBOOK_PAGE_ACCESS_TOKEN' => $request->input('facebook_page_access_token'),
            'GEMINI_API_KEY' => $request->input('gemini_api_key'),
            'META_GRAPH_VERSION' => $request->input('meta_graph_version'),
            'FACEBOOK_PUBLISH_MODE' => $request->input('facebook_publish_mode'),
        ];

        foreach ($fields as $key => $value) {
            // Skip masked values — user didn't change the secret
            if ($value && preg_match('/^•+/', $value)) {
                continue;
            }
            $isSecret = in_array(strtolower($key), $secretFields);
            Setting::setValue($key, $value, $isSecret);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Settings saved successfully.');
    }

    /**
     * Validate Facebook configuration by calling Graph API.
     */
    public function validateFacebook()
    {
        $service = new FacebookPageService();
        $result = $service->validateConfig();

        if ($result['success']) {
            return redirect()->route('settings.index')
                ->with('success', "✅ Facebook Page validated: {$result['page_name']} ({$result['page_id']})");
        } else {
            return redirect()->route('settings.index')
                ->with('error', "❌ Validation failed: {$result['message']}");
        }
    }
}
