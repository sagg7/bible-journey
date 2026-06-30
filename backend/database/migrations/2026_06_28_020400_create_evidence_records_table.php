<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_records', function (Blueprint $table) {
            $table->id();

            $table->string('source_key', 64)->unique()->comment('e.g. EVD-DAV-001-P1');

            // The claim this evidence supports
            $table->text('claim');

            // Source reference
            $table->string('source_reference', 128)->nullable()->comment('e.g. 1 Samuel 16:1–13');
            $table->string('source_book', 64)->nullable();

            $table->enum('evidence_type', [
                'biblical_text_direct',
                'historical_context',
                'editorial_inference',
                'traditional_attribution',
                'scholarly_consensus',
                'speculative',
            ])->default('biblical_text_direct');

            $table->enum('confidence', ['alta', 'probable', 'debatida', 'tradicion_popular', 'especulativa'])->default('probable');

            $table->enum('review_status', ['approved', 'needs_review', 'draft', 'flagged'])->default('needs_review');
            $table->string('reviewer', 128)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_records');
    }
};
