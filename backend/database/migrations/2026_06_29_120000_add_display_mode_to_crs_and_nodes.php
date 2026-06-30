<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add display_mode hint to CRS so the resolver can honour it
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->string('display_mode', 50)->nullable()->after('is_main_stream_node');
        });

        // Extend the stream_plan_nodes enum to include the new unresolved_prophetic_window mode
        DB::statement("ALTER TABLE stream_plan_nodes MODIFY COLUMN display_mode
            ENUM('full','narrative_flow','reference_only','unresolved_prophetic_window')
            NOT NULL DEFAULT 'full'");
    }

    public function down(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });

        DB::statement("ALTER TABLE stream_plan_nodes MODIFY COLUMN display_mode
            ENUM('full','narrative_flow','reference_only')
            NOT NULL DEFAULT 'full'");
    }
};
