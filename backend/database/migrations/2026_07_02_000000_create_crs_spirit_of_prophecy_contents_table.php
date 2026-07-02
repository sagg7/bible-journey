<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crs_spirit_of_prophecy_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crs_id')
                ->constrained('chronological_reading_sets')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('source_book_code')->nullable();
            $table->string('source_book_title')->nullable();
            $table->json('excerpts')->nullable();
            $table->string('content_version')->default('egw-v1');
            $table->timestamps();

            $table->unique(['crs_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crs_spirit_of_prophecy_contents');
    }
};
