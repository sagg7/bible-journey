<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crs_study_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crs_id')
                ->unique()
                ->constrained('chronological_reading_sets')
                ->cascadeOnDelete();
            $table->text('summary_es')->nullable();
            $table->text('context_es')->nullable();
            $table->json('people')->nullable();
            $table->json('places')->nullable();
            $table->json('connections')->nullable();
            $table->json('sources')->nullable();
            $table->string('content_version')->default('auto-v1');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crs_study_contents');
    }
};
