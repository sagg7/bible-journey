<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_canonical_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('reading_blocks')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('stream_plans')->cascadeOnDelete();

            // Plan version when this was recorded — guards against stale progress
            $table->string('plan_version', 64)->nullable();

            $table->enum('status', [
                'not_started',
                'in_progress',
                'completed',
                'deferred',  // skipped via Narrative Flow
                'skipped',   // explicitly skipped
            ])->default('not_started');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'block_id', 'plan_id'], 'unique_canonical_progress');
            $table->index(['user_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_canonical_progress');
    }
};
