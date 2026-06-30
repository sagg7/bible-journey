<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('crs_id')->constrained('chronological_reading_sets')->cascadeOnDelete();
            $table->string('source_map', 32)->comment('e.g. BLK-DAV-001-01');

            // Passage
            $table->string('book', 64);
            $table->string('passage_start', 32)->comment('e.g. 1Sam.16.1');
            $table->string('passage_end', 32)->comment('e.g. 1Sam.16.13');
            $table->string('display_reference', 64)->comment('e.g. 1 Samuel 16:1–13');

            // Role
            $table->enum('role', [
                'narrative_anchor',
                'parallel_account',
                'complementary_account',
                'prophetic_context',
                'poetic_literary_mirror',
                'legal_covenant_context',
                'genealogical_context',
                'epistolary_context',
                'supplementary_reading',
            ])->default('narrative_anchor');

            // Display
            $table->integer('display_order')->default(0);
            $table->string('display_label_es')->nullable();
            $table->string('display_label_en')->nullable();

            // Modes
            $table->boolean('required_in_complete_mode')->default(true);
            $table->boolean('shown_in_narrative_flow')->default(true);

            // Confidence
            $table->enum('placement_confidence', ['alta', 'probable', 'debatida', 'tradicion_popular', 'especulativa'])->default('probable');

            // Source keys (JSON array of evidence record references)
            $table->json('source_keys')->nullable();

            // Link to existing passage data if available
            $table->foreignId('passage_id')->nullable()->constrained('passages')->nullOnDelete();

            $table->timestamps();

            $table->index(['crs_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_blocks');
    }
};
