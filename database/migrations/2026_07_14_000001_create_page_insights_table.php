<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_insights', function (Blueprint $table) {
            $table->id();
            $table->string('metric');
            $table->string('period');
            $table->json('values_json');
            $table->date('fetched_date');
            $table->timestamps();

            $table->unique(['metric', 'period', 'fetched_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_insights');
    }
};
