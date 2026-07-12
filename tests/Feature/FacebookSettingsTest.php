<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_config_success(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'valid-token', true);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789',
                'name' => 'My Page',
                'link' => 'https://facebook.com/mypage',
            ], 200),
        ]);

        $response = $this->post('/settings/facebook/validate');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');
    }

    public function test_validate_config_401_returns_error(): void
    {
        Setting::setValue('FACEBOOK_PAGE_ID', '123456789');
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'bad-token', true);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid token', 'type' => 'OAuthException', 'code' => 190],
            ], 401),
        ]);

        $response = $this->post('/settings/facebook/validate');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('error');
    }

    public function test_save_settings_does_not_return_plain_token(): void
    {
        // First, save a token
        $this->post('/settings', [
            'pexels_api_key' => 'pexels-key-123',
            'facebook_page_id' => '123456789',
            'facebook_page_access_token' => 'super-secret-token-should-not-appear',
            'gemini_api_key' => 'gemini-key-456',
            'meta_graph_version' => 'v25.0',
            'facebook_publish_mode' => 'fake',
        ]);

        // Then load the settings page
        $response = $this->get('/settings');

        $response->assertStatus(200);

        // The token should be masked in the response
        $pageProps = $response->original->getData()['page']['props'] ?? [];
        $settings = $pageProps['settings'] ?? [];

        if (!empty($settings['facebook_page_access_token'])) {
            $this->assertStringNotContainsString(
                'super-secret-token-should-not-appear',
                $settings['facebook_page_access_token']
            );
            // Should contain masked characters
            $this->assertStringContainsString('•', $settings['facebook_page_access_token']);
        }
    }

    public function test_save_settings_stores_facebook_config(): void
    {
        $response = $this->post('/settings', [
            'pexels_api_key' => '',
            'facebook_page_id' => '999888777',
            'facebook_page_access_token' => 'my-token-xyz',
            'gemini_api_key' => '',
            'meta_graph_version' => 'v25.0',
            'facebook_publish_mode' => 'fake',
        ]);

        $response->assertRedirect('/settings');

        $this->assertDatabaseHas('settings', [
            'key' => 'FACEBOOK_PAGE_ID',
            'value' => '999888777',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'FACEBOOK_PAGE_ACCESS_TOKEN',
            'value' => 'my-token-xyz',
            'is_secret' => true,
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'FACEBOOK_PUBLISH_MODE',
            'value' => 'fake',
        ]);
    }
}
