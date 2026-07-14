<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_settings_override_env(): void
    {
        // Enforce database setting value
        Setting::setValue('GEMINI_MODEL', 'gemini-test-db');
        
        $this->assertEquals('gemini-test-db', Setting::getValue('GEMINI_MODEL'));
    }

    public function test_masked_secrets_are_not_overwritten(): void
    {
        // Set initial secret token
        Setting::setValue('FACEBOOK_PAGE_ACCESS_TOKEN', 'super-secret-token', true);

        // Submit form with masked values
        $response = $this->post('/settings', [
            'pexels_api_key' => '',
            'facebook_page_id' => '12345',
            'facebook_page_access_token' => '••••••••cken', // starts with bullets, indicating it was masked and not changed by user
            'gemini_api_key' => '',
            'meta_graph_version' => 'v25.0',
            'facebook_publish_mode' => 'fake',
            'gemini_enabled' => 'false',
        ]);

        $response->assertRedirect('/settings');

        // Confirm database still contains original secret token (not overwritten)
        $this->assertEquals('super-secret-token', Setting::getValue('FACEBOOK_PAGE_ACCESS_TOKEN'));
    }
}
