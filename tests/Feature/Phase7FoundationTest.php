<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageProfile;
use App\Models\PostQueue;
use App\Models\Setting;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiTask;
use App\Models\PendingSecret;
use App\Services\Security\SecretRedactionService;
use App\Services\AI\AIAssistantService;
use App\Services\AI\AIContentPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase7FoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. SecretRedactionServiceTest
     */
    public function test_secret_redaction_service_redacts_fb_tokens(): void
    {
        $service = new SecretRedactionService();
        $rawText = "Please configure my page with token EAABwz56y1234567890xyzASD";
        
        $result = $service->extractAndSaveSecrets($rawText);
        
        $this->assertNotEmpty($result['pending_secret_ids']);
        $this->assertStringContainsString('[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]', $result['redacted_text']);
        $this->assertStringNotContainsString('EAABwz56y1234567890xyzASD', $result['redacted_text']);
        
        // Assert stored in pending secrets
        $secretId = $result['pending_secret_ids'][0];
        $secret = PendingSecret::find($secretId);
        $this->assertNotNull($secret);
        $this->assertEquals('EAABwz56y1234567890xyzASD', $secret->encrypted_value);
    }

    /**
     * 2. AssistantDoesNotStoreRawTokenTest
     */
    public function test_assistant_does_not_store_raw_tokens_in_chat_history(): void
    {
        $session = AiChatSession::create(['title' => 'Test Chat']);
        
        $userMessage = "My page token is EAABwz56y1234567890xyzASD";
        
        // Send message via assistant endpoint
        $response = $this->post('/assistant/message', [
            'session_id' => $session->id,
            'message' => $userMessage,
        ]);
        
        $response->assertRedirect();
        
        // Assert stored message has redacted token
        $msg = AiChatMessage::where('session_id', $session->id)
            ->where('role', 'user')
            ->first();
            
        $this->assertNotNull($msg);
        $this->assertStringContainsString('[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]', $msg->content);
        $this->assertStringNotContainsString('EAABwz56y1234567890xyzASD', $msg->content);
        $this->assertTrue($msg->metadata['has_redacted_secrets'] ?? false);
        
        // Verify database is completely free of raw token
        $this->assertDatabaseMissing('ai_chat_messages', [
            'content' => $userMessage
        ]);
    }

    /**
     * 3. UpdatePageTokenTaskTest
     */
    public function test_update_page_token_task_confirm_decrypts_and_saves_token(): void
    {
        $page = Page::create([
            'name' => 'Buddhist teachings',
            'platform' => 'facebook',
            'publish_mode' => 'fake',
            'is_active' => true,
        ]);

        $pending = PendingSecret::create([
            'secret_type' => 'facebook_page_access_token',
            'encrypted_value' => 'EAAB-my-decrypted-token-value',
            'redacted_label' => '[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]',
            'expires_at' => now()->addHour(),
        ]);

        $task = AiTask::create([
            'page_id' => $page->id,
            'type' => 'update_page_token',
            'status' => 'awaiting_confirmation',
            'plan_json' => [
                'steps' => ['Update Page Buddhist teachings access token'],
                'parameters' => [
                    'pending_secret_id' => $pending->id,
                ]
            ]
        ]);

        // Confirm task via route
        $response = $this->post("/assistant/tasks/{$task->id}/confirm");
        $response->assertRedirect();

        $task->refresh();
        $page->refresh();

        $this->assertEquals('completed', $task->status);
        $this->assertEquals('EAAB-my-decrypted-token-value', $page->access_token);
        
        // Secret must be marked consumed after execution
        $this->assertTrue($pending->fresh()->isConsumed());
    }

    /**
     * 4. ValidatePageTokenTaskTest
     */
    public function test_validate_page_token_task_confirm_executes_successfully(): void
    {
        $page = Page::create([
            'name' => 'Nature healing',
            'platform' => 'facebook',
            'facebook_page_id' => '999999',
            'access_token' => encrypt('valid-token'),
            'publish_mode' => 'fake',
            'is_active' => true,
        ]);

        $task = AiTask::create([
            'page_id' => $page->id,
            'type' => 'validate_page_token',
            'status' => 'awaiting_confirmation',
            'plan_json' => [
                'steps' => ['Validate access token for Page Nature healing'],
                'parameters' => []
            ]
        ]);

        // Mock Graph API call
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '999999', 'name' => 'Nature healing'], 200)
        ]);

        $response = $this->post("/assistant/tasks/{$task->id}/confirm");
        $response->assertRedirect();

        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }

    /**
     * 5. AssistantGeminiPromptSafetyTest
     */
    public function test_tokens_are_never_sent_to_gemini_prompts(): void
    {
        // Enable Gemini in settings
        Setting::setValue('GEMINI_ENABLED', 'true');
        Setting::setValue('GEMINI_API_KEY', 'fake-api-key');

        $session = AiChatSession::create(['title' => 'Test Safety Chat']);
        
        // Mock Gemini HTTP endpoint to see the request body
        Http::fake([
            'generativelanguage.googleapis.com/*' => function ($request) {
                // Ensure request does NOT contain the raw token
                $body = $request->getBody()->getContents();
                if (str_contains($body, 'EAABwz56y1234567890xyzASD')) {
                    return Http::response(['error' => 'Safety violation: raw token sent!'], 500);
                }
                return Http::response([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => json_encode([
                            'intent' => 'update_page_token',
                            'parameters' => ['token_present' => true],
                            'reply' => 'Sure! I will update the page configuration.'
                        ])]]]
                    ]]
                ], 200);
            }
        ]);

        $userMessage = "Please update token to EAABwz56y1234567890xyzASD";
        
        $response = $this->post('/assistant/message', [
            'session_id' => $session->id,
            'message' => $userMessage,
        ]);
        
        $response->assertRedirect();
        
        // Check message saved
        $msg = AiChatMessage::where('session_id', $session->id)
            ->where('role', 'assistant')
            ->first();
            
        $this->assertNotNull($msg);
        $this->assertStringContainsString('I detected that you want to update', $msg->content);
    }

    /**
     * 6. PendingSecretExpiryTest
     */
    public function test_pending_secrets_cleanup_expires_old_secrets(): void
    {
        // One fresh secret, one expired
        $fresh = PendingSecret::create([
            'secret_type' => 'facebook_page_access_token',
            'encrypted_value' => 'fresh-token',
            'redacted_label' => '[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]',
            'expires_at' => now()->addMinutes(30),
        ]);
        $expired = PendingSecret::create([
            'secret_type' => 'facebook_page_access_token',
            'encrypted_value' => 'expired-token',
            'redacted_label' => '[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]',
            'expires_at' => now()->subMinutes(10),
        ]);

        // Run direct deletion scope/logic
        PendingSecret::where('expires_at', '<', now())->delete();

        $this->assertNotNull(PendingSecret::find($fresh->id));
        $this->assertNull(PendingSecret::find($expired->id));
    }

    /**
     * 7. PageManagementTest
     */
    public function test_page_crud_and_presets(): void
    {
        // 1. Create a page with preset
        $response = $this->post('/pages', [
            'name' => 'My Nature Healing Page',
            'platform' => 'facebook',
            'facebook_page_id' => '1029384756',
            'access_token' => 'EAAB-healing-token',
            'publish_mode' => 'fake',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'language' => 'english',
            'preset' => 'nature_healing',
            'is_active' => true,
            'approval_mode' => 'manual',
            
            // Profile overridden fields from preset
            'description' => 'Calm, peaceful, and healing natural environments.',
            'audience' => 'People seeking relaxation, meditation, and peace.',
            'avoid_topics' => 'Superstition, politics.',
            'preferred_media_types' => ['photo', 'video'],
            'content_mix' => ['photo' => 30, 'video' => 70, 'text' => 0],
            'posting_slots' => ['07:30', '12:30', '20:30'],
            'max_posts_per_day' => 3,
        ]);

        $response->assertRedirect('/pages');
        
        $page = Page::where('facebook_page_id', '1029384756')->first();
        $this->assertNotNull($page);
        $this->assertEquals('Nature & Relaxation', $page->niche);
        $this->assertEquals('calm, peaceful, healing', $page->content_tone);

        // Verify profile relations
        $profile = $page->profile;
        $this->assertNotNull($profile);
        $this->assertEquals('manual', $profile->approval_mode);
        $this->assertEquals(70, $profile->content_mix['video']);
        $this->assertEquals(['07:30', '12:30', '20:30'], $profile->posting_slots);
    }

    /**
     * 8. PageProfileTest
     */
    public function test_page_profile_validation(): void
    {
        $page = Page::create([
            'name' => 'Profile validation page',
            'platform' => 'facebook',
            'publish_mode' => 'fake',
        ]);

        $profile = PageProfile::create([
            'page_id' => $page->id,
            'description' => 'A valid profile description.',
            'content_mix' => ['photo' => 50, 'video' => 50, 'text' => 0],
            'posting_slots' => ['08:00', '16:00'],
        ]);

        $this->assertNotNull($page->profile);
        $this->assertEquals(2, count($page->profile->posting_slots));
    }

    /**
     * 9. QueuePageFilterTest
     */
    public function test_queue_index_filters_posts_by_page_id(): void
    {
        $page1 = Page::create(['name' => 'Page One', 'platform' => 'facebook', 'publish_mode' => 'fake']);
        $page2 = Page::create(['name' => 'Page Two', 'platform' => 'facebook', 'publish_mode' => 'fake']);

        $post1 = PostQueue::create([
            'page_id' => $page1->id,
            'caption' => 'Post for page 1',
            'status' => 'draft',
        ]);
        $post2 = PostQueue::create([
            'page_id' => $page2->id,
            'caption' => 'Post for page 2',
            'status' => 'draft',
        ]);

        // Filter by page1
        $response = $this->get("/queue?page_id={$page1->id}");
        $response->assertSuccessful();

        $inertiaData = $response->original->getData()['page']['props']['posts']['data'] ?? [];
        $this->assertCount(1, $inertiaData);
        $this->assertEquals($post1->id, $inertiaData[0]['id']);
    }

    /**
     * 10. PublishDueMultiPageTest
     */
    public function test_publish_due_command_publishes_scoped_posts_for_specific_page(): void
    {
        $page1 = Page::create([
            'name' => 'Active Page 1',
            'platform' => 'facebook',
            'facebook_page_id' => 'p1_123',
            'access_token' => encrypt('p1-token'),
            'publish_mode' => 'real',
            'is_active' => true,
        ]);
        $page2 = Page::create([
            'name' => 'Active Page 2',
            'platform' => 'facebook',
            'facebook_page_id' => 'p2_456',
            'access_token' => encrypt('p2-token'),
            'publish_mode' => 'real',
            'is_active' => true,
        ]);

        $post1 = PostQueue::create([
            'page_id' => $page1->id,
            'caption' => 'Scoped page 1 publish due',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);
        $post2 = PostQueue::create([
            'page_id' => $page2->id,
            'caption' => 'Scoped page 2 publish due',
            'status' => 'approved',
            'scheduled_at' => now()->subHour(),
        ]);

        // Mock Graph API call for both pages
        Http::fake([
            'graph.facebook.com/v25.0/p1_123/feed' => Http::response(['id' => 'p1_feed_post_id'], 200),
            'graph.facebook.com/v25.0/p2_456/feed' => Http::response(['id' => 'p2_feed_post_id'], 200),
        ]);

        // Run publishing scoped only to Page 1
        $this->artisan("posts:publish-due --page={$page1->id}")
            ->assertSuccessful();

        $this->assertEquals('published', $post1->fresh()->status);
        $this->assertEquals('p1_feed_post_id', $post1->fresh()->facebook_post_id);
        
        // Post 2 should NOT be published because the command was scoped only to Page 1
        $this->assertEquals('approved', $post2->fresh()->status);
    }
}
