<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblical_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter_start');
            $table->unsignedSmallInteger('verse_start')->nullable();
            $table->unsignedSmallInteger('chapter_end')->nullable();
            $table->unsignedSmallInteger('verse_end')->nullable();
            $table->string('reference_label');             // "1 Samuel 21:1-15" (display, language-neutral base)
            $table->timestamps();

            $table->index(['biblical_book_id', 'chapter_start', 'verse_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passages');
    }
};
