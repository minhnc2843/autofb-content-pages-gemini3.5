<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create ai_chat_sessions table
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status')->default('active'); // active, archived
            $table->timestamps();
        });

        // Create ai_chat_messages table
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
            $table->string('role'); // user, assistant, system
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Create ai_tasks table
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained('ai_chat_sessions')->nullOnDelete();
            $table->string('type'); // create_weekly_plan, generate_drafts, etc.
            $table->string('status')->default('draft'); // draft, awaiting_confirmation, running, completed, failed, cancelled
            $table->longText('user_prompt')->nullable();
            $table->json('plan_json')->nullable();
            $table->json('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('requires_confirmation')->default(true);
            $table->timestamps();
        });

        // Create pending_secrets table
        Schema::create('pending_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('user_session_id')->nullable();
            $table->foreignId('ai_chat_session_id')->nullable()->constrained('ai_chat_sessions')->nullOnDelete();
            $table->string('secret_type'); // facebook_page_access_token, etc.
            $table->longText('encrypted_value'); // encrypted string
            $table->string('redacted_label'); // e.g. [FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_secrets');
        Schema::dropIfExists('ai_tasks');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
