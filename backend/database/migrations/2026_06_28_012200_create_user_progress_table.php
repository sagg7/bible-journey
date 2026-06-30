<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_event_id')->nullable()
                ->constrained('historical_events')->nullOnDelete();
            $table->json('completed_events')->nullable();   // [event_id, ...]
            $table->unsignedInteger('streak_count')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
