<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_event_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('context')->nullable();
            $table->string('review_status')->default('draft');
            $table->timestamps();

            $table->unique(['historical_event_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_translations');
    }
};
