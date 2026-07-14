<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_queue_id')->constrained('posts_queue')->onDelete('cascade');
            $table->string('from_status');
            $table->string('to_status');
            $table->string('changed_by')->default('system');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_status_histories');
    }
};
