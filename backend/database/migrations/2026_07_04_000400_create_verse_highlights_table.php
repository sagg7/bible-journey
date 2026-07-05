<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verse_highlights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('highlight_color_id')->constrained('highlight_colors')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('biblical_books')->cascadeOnDelete();

            $table->unsignedInteger('chapter_number');
            $table->unsignedInteger('verse_start');
            $table->unsignedInteger('verse_end');

            $table->timestamps();

            $table->unique(
                ['user_id', 'book_id', 'chapter_number', 'verse_start', 'verse_end'],
                'unique_user_verse_range'
            );
            $table->index(['user_id', 'highlight_color_id']);
            $table->index(['user_id', 'book_id', 'chapter_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verse_highlights');
    }
};
