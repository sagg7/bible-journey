<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan versioning: tracks parent/child plan relationships
        Schema::table('stream_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_plan_id')->nullable()->after('id');
            $table->foreign('parent_plan_id')->references('id')->on('stream_plans')->nullOnDelete();
            $table->string('version', 20)->nullable()->after('parent_plan_id');
            $table->text('purpose')->nullable()->after('version');
        });

        // Complete-mode classification: why a chapter is required in Complete Chronological Reading
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->enum('complete_mode_behavior', [
                'main_required',              // main_historical_event, is_main_stream_node=1
                'required_window',            // literary_collection, editorial_context, genealogy_context
                'required_associated_reading',// associated_poetry
                'required_canonical_fallback',// canonical_fallback
                'required_prophetic_window',  // prophetic_context, unresolved_prophetic_window
                'required_epistolary_window', // epistolary_context (non-main)
                'required_apocalyptic_sequence', // apocalyptic_literary_sequence
                'optional_in_narrative',      // historical_bridge (no reading blocks)
                'context_only',               // no chapter coverage, metadata only
            ])->nullable()->after('is_main_stream_node');
        });
    }

    public function down(): void
    {
        Schema::table('stream_plans', function (Blueprint $table) {
            $table->dropForeign(['parent_plan_id']);
            $table->dropColumn(['parent_plan_id', 'version', 'purpose']);
        });

        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->dropColumn('complete_mode_behavior');
        });
    }
};
