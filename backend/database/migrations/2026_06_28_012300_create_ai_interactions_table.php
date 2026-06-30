<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de interacciones con Ezra (IA guía). Sirve para cache, control de costos y auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('historical_event_id')->nullable()
                ->constrained('historical_events')->nullOnDelete();
            $table->string('locale', 5)->nullable();
            $table->text('question');
            $table->longText('answer');
            $table->json('citations')->nullable();
            $table->string('model_used')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('token_cost', 10, 6)->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->timestamps();

            $table->index(['historical_event_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
