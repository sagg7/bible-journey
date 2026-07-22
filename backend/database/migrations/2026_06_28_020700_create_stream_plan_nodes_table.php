<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_plan_nodes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')->constrained('stream_plans')->cascadeOnDelete();
            $table->foreignId('crs_id')->constrained('chronological_reading_sets')->cascadeOnDelete();

            // Position in the compiled stream
            $table->unsignedInteger('rank')->default(0);

            $table->enum('display_mode', [
                'full',           // all blocks shown
                'narrative_flow', // only narrative_anchor shown, related deferred
                'reference_only', // no full text — external Bible required
                'unresolved_prophetic_window', // added 2026-06-29 (120000)
                'historical_bridge',           // added 2026-06-29 (150000)
            ])->default('full');

            // State required for this node to be accessible
            $table->enum('required_state', [
                'none',           // always accessible
                'previous_complete', // previous node must be narrative_complete
                'previous_full',  // previous node must be fully_complete
            ])->default('none');

            // Why this node is placed here (shown in Study Mode)
            $table->text('explanation_es')->nullable();
            $table->text('explanation_en')->nullable();

            // Link to a compare group if applicable
            $table->foreignId('compare_group_id')->nullable()->constrained('compare_groups')->nullOnDelete();

            $table->timestamps();

            $table->unique(['plan_id', 'crs_id']);
            $table->index(['plan_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_plan_nodes');
    }
};
