<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add historical_bridge to stream_plan_nodes.display_mode enum.
        // MODIFY COLUMN is MySQL/MariaDB-only; on sqlite (tests) the create migration
        // already declares the full value set, so nothing to do.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE stream_plan_nodes MODIFY COLUMN display_mode
                ENUM('full','narrative_flow','reference_only','unresolved_prophetic_window','historical_bridge')
                NOT NULL DEFAULT 'full'");
        }

        // 2. Coverage paths table — one row per book/chapter/plan
        Schema::create('chronological_coverage_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('stream_plans')->cascadeOnDelete();
            $table->foreignId('bible_book_id')->constrained('biblical_books');
            $table->unsignedSmallInteger('chapter');

            // Primary node that covers this chapter
            $table->foreignId('primary_stream_plan_node_id')
                  ->nullable()
                  ->constrained('stream_plan_nodes')
                  ->nullOnDelete();

            $table->string('parent_era', 100)->nullable();
            $table->unsignedSmallInteger('parent_era_sort')->nullable();

            // How this chapter is reached
            $table->foreignId('entry_point_node_id')
                  ->nullable()
                  ->constrained('stream_plan_nodes')
                  ->nullOnDelete();

            // Path classification (mirrors stream_role but at chapter level)
            $table->string('display_mode', 50)->default('main_historical_event');

            $table->boolean('complete_mode_required')->default(true);

            $table->enum('narrative_flow_behavior', [
                'included',        // shown inline in narrative flow
                'pending',         // deferred, must be recovered later
                'optional',        // user can skip
                'excluded',        // not in narrative flow at all
            ])->default('included');

            $table->boolean('is_user_reachable')->default(false);
            $table->text('rationale')->nullable();
            $table->string('placement_confidence', 20)->nullable();

            $table->timestamps();

            // Unique: one primary path per chapter per plan
            $table->unique(['plan_id', 'bible_book_id', 'chapter'], 'idx_coverage_unique');
            $table->index(['plan_id', 'is_user_reachable'], 'idx_coverage_reachable');
            $table->index(['plan_id', 'display_mode'], 'idx_coverage_display_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chronological_coverage_paths');

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE stream_plan_nodes MODIFY COLUMN display_mode
                ENUM('full','narrative_flow','reference_only','unresolved_prophetic_window')
                NOT NULL DEFAULT 'full'");
        }
    }
};
