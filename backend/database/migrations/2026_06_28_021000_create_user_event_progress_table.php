<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_event_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('stream_plan_nodes')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('stream_plans')->cascadeOnDelete();

            $table->string('plan_version', 64)->nullable();

            // Narrative progress state — tracks event advancement, NOT canonical coverage
            $table->enum('state', [
                'not_started',
                'in_progress',
                'primary_complete',       // narrative_anchor read, related blocks deferred
                'narrative_complete',     // user chose "Continuar la historia" — related deferred
                'fully_complete',         // all blocks read
                'deferred',              // Narrative Flow: come back later
            ])->default('not_started');

            // How many related blocks remain pending
            $table->unsignedSmallInteger('pending_block_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('primary_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'node_id', 'plan_id'], 'unique_event_progress');
            $table->index(['user_id', 'plan_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_event_progress');
    }
};
