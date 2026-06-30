<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chronological_reading_sets', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('source_map', 32)->unique()->comment('e.g. CRS-DAV-001');
            $table->string('era', 64)->index()->comment('e.g. Monarquía unida');
            $table->string('era_slug', 64)->index();
            $table->integer('sort_key')->default(0)->index();

            // Titles
            $table->string('title_es');
            $table->string('title_en')->nullable();

            // Confidence
            $table->enum('placement_confidence', ['alta', 'probable', 'debatida', 'tradicion_popular', 'especulativa'])->default('probable');
            $table->enum('event_confidence',     ['alta', 'probable', 'debatida', 'tradicion_popular', 'especulativa'])->default('probable');
            $table->enum('relation_confidence',  ['alta', 'probable', 'debatida', 'tradicion_popular', 'especulativa'])->default('probable');

            // Editorial
            $table->enum('review_status', ['approved', 'needs_review', 'draft', 'blocked'])->default('needs_review');
            $table->string('editorial_version', 16)->default('1.0');
            $table->text('narrative_flow_message_es')->nullable();
            $table->text('transition_copy_es')->nullable();
            $table->text('editorial_note')->nullable();
            $table->string('canon_profile', 32)->default('cautious_default');

            // Link to existing historical_events pilot data
            $table->foreignId('historical_event_id')->nullable()->constrained('historical_events')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chronological_reading_sets');
    }
};
