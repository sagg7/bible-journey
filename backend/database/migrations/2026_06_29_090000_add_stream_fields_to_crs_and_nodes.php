<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            // Roles for the reading in the stream plan
            $table->string('stream_role', 64)->nullable()->after('era_slug')
                ->comment('main_historical_event|parallel_account|complementary_account|prophetic_context|associated_poetry|literary_collection|canonical_fallback|editorial_context|composition_context|genealogy_context|legal_context|epistolary_context|apocalyptic_literary_sequence');

            // User-facing display — what the reader sees as section header
            $table->string('user_facing_era', 100)->nullable()->after('stream_role');
            $table->unsignedSmallInteger('user_facing_era_sort')->nullable()->after('user_facing_era');

            // Controls visibility in the Chronological Stream timeline
            $table->boolean('is_main_stream_node')->default(true)->after('user_facing_era_sort');

            $table->index(['is_main_stream_node', 'user_facing_era_sort'], 'idx_crs_stream_visibility');
        });

        Schema::table('stream_plan_nodes', function (Blueprint $table) {
            $table->string('stream_role', 64)->nullable()->after('required_state');
            $table->string('user_facing_era', 100)->nullable()->after('stream_role');
            $table->unsignedSmallInteger('user_facing_era_sort')->nullable()->after('user_facing_era');
            $table->boolean('is_main_stream_node')->default(true)->after('user_facing_era_sort');

            $table->index(['is_main_stream_node', 'rank'], 'idx_spn_stream_main');
        });
    }

    public function down(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->dropIndex('idx_crs_stream_visibility');
            $table->dropColumn(['stream_role', 'user_facing_era', 'user_facing_era_sort', 'is_main_stream_node']);
        });

        Schema::table('stream_plan_nodes', function (Blueprint $table) {
            $table->dropIndex('idx_spn_stream_main');
            $table->dropColumn(['stream_role', 'user_facing_era', 'user_facing_era_sort', 'is_main_stream_node']);
        });
    }
};
