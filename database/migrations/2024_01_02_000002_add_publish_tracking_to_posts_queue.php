<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts_queue', function (Blueprint $table) {
            $table->timestamp('publish_started_at')->nullable()->after('scheduled_at');
            $table->timestamp('published_at')->nullable()->after('publish_started_at');
            $table->unsignedInteger('publish_attempts')->default(0)->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts_queue', function (Blueprint $table) {
            $table->dropColumn(['publish_started_at', 'published_at', 'publish_attempts']);
        });
    }
};
