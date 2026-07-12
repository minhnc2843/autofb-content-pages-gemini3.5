<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->foreignId('media_item_id')->nullable()->constrained('media_items')->nullOnDelete();
            $table->longText('caption');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('facebook_post_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts_queue');
    }
};
