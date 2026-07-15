<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    public function up(): void
    {
        // Create pages table
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('platform')->default('facebook');
            $table->string('facebook_page_id')->nullable();
            $table->string('facebook_page_name')->nullable();
            $table->text('facebook_page_link')->nullable();
            $table->text('access_token')->nullable(); // encrypted
            $table->dateTime('token_expires_at')->nullable();
            $table->string('publish_mode')->default('fake'); // fake or real
            $table->boolean('is_active')->default(true);
            $table->string('timezone')->default('Asia/Ho_Chi_Minh');
            $table->string('language')->default('english');
            $table->string('country')->nullable();
            $table->string('niche')->nullable();
            $table->string('content_tone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Create page_profiles table
        Schema::create('page_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->text('audience')->nullable();
            $table->text('content_goals')->nullable();
            $table->text('avoid_topics')->nullable();
            $table->json('preferred_media_types')->nullable();
            $table->json('content_mix')->nullable();
            $table->json('posting_slots')->nullable();
            $table->string('approval_mode')->default('manual'); // manual, semi_auto, full_auto
            $table->integer('auto_approve_min_score')->nullable()->default(85);
            $table->integer('max_posts_per_day')->default(3);
            $table->string('hashtag_policy')->nullable();
            $table->string('language_policy')->nullable();
            $table->timestamps();
        });

        // Create page_topics table
        Schema::create('page_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('name');
            $table->string('keyword');
            $table->string('language')->nullable();
            $table->integer('priority')->default(5);
            $table->integer('cooldown_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add page_id to existing tables
        Schema::table('posts_queue', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->after('id')->constrained('pages')->nullOnDelete();
        });

        Schema::table('media_items', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->after('id')->constrained('pages')->nullOnDelete();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->after('id')->constrained('pages')->nullOnDelete();
        });

        Schema::table('post_publish_logs', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->after('id')->constrained('pages')->nullOnDelete();
        });

        Schema::table('page_insights', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->after('id')->constrained('pages')->nullOnDelete();
        });

        // Migrate Default Page if global settings are present
        try {
            $fbPageId = Setting::getValue('FACEBOOK_PAGE_ID');
            $fbToken = Setting::getValue('FACEBOOK_PAGE_ACCESS_TOKEN');
            $fbMode = Setting::getValue('FACEBOOK_PUBLISH_MODE', 'fake');

            if ($fbPageId || $fbToken) {
                $encryptedToken = null;
                if ($fbToken) {
                    $encryptedToken = Crypt::encryptString($fbToken);
                }

                $pageId = DB::table('pages')->insertGetId([
                    'name' => 'Default Facebook Page',
                    'slug' => 'default-facebook-page',
                    'platform' => 'facebook',
                    'facebook_page_id' => $fbPageId,
                    'facebook_page_name' => 'Default Page',
                    'access_token' => $encryptedToken,
                    'publish_mode' => $fbMode ?: 'fake',
                    'is_active' => true,
                    'timezone' => config('app.timezone', 'Asia/Ho_Chi_Minh'),
                    'language' => 'english',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('page_profiles')->insert([
                    'page_id' => $pageId,
                    'preferred_media_types' => json_encode(['photo', 'video']),
                    'content_mix' => json_encode(['photo' => 50, 'video' => 50, 'text' => 0]),
                    'posting_slots' => json_encode(['07:30', '12:30', '20:30']),
                    'approval_mode' => 'manual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Log or ignore during migration if settings table is not seeded yet or encryption fails
            Log::warning('Failed to seed default page during migration: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('page_insights', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        Schema::table('post_publish_logs', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        Schema::table('media_items', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        Schema::table('posts_queue', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        Schema::dropIfExists('page_topics');
        Schema::dropIfExists('page_profiles');
        Schema::dropIfExists('pages');
    }
};
