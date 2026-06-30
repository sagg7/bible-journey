<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compare_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('crs_id')->constrained('chronological_reading_sets')->cascadeOnDelete();
            $table->string('source_map', 32)->nullable()->comment('e.g. CMP-DAV-001');

            $table->string('title_es');
            $table->string('title_en')->nullable();

            $table->text('editorial_summary_es')->nullable();
            $table->text('editorial_summary_en')->nullable();

            // Mandatory disclaimer shown above the comparison
            $table->text('disclaimer_es')->nullable();
            $table->text('disclaimer_en')->nullable();

            $table->enum('relation_level', ['identical', 'parallel', 'complementary', 'related', 'thematic'])->default('parallel');

            // Key differences (stored as JSON array of strings)
            $table->json('key_differences_es')->nullable();
            $table->json('key_differences_en')->nullable();

            $table->enum('review_status', ['approved', 'needs_review', 'draft', 'blocked'])->default('needs_review');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compare_groups');
    }
};
