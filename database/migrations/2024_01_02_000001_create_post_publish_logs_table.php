<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_queue_id')->nullable()->constrained('posts_queue')->nullOnDelete();
            $table->string('mode'); // fake, real
            $table->string('provider')->default('facebook');
            $table->string('action'); // validate_config, publish_text, publish_photo, publish_video, publish_due
            $table->string('status'); // success, failed
            $table->json('request_summary')->nullable();
            $table->json('response_json')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_publish_logs');
    }
};
