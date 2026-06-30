<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend biblical_books with translatable name fields and chapter count
        Schema::table('biblical_books', function (Blueprint $table) {
            $table->string('name_es')->nullable()->after('osis_code');
            $table->string('name_en')->nullable()->after('name_es');
            $table->unsignedSmallInteger('chapter_count')->nullable()->after('name_en');
        });

        // Extend translations table with provenance fields
        Schema::table('translations', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->after('attribution');
            $table->string('source_file_hash', 64)->nullable()->after('source_url');
            $table->timestamp('imported_at')->nullable()->after('source_file_hash');
            $table->string('license_label')->nullable()->after('imported_at');
        });

        // Bible chapters — one row per chapter per book
        Schema::create('bible_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblical_book_id')->constrained('biblical_books')->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter_number');
            $table->unsignedSmallInteger('verse_count')->default(0);
            $table->timestamps();

            $table->unique(['biblical_book_id', 'chapter_number']);
        });

        // Bible verses — one row per verse per translation
        Schema::create('bible_verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('bible_chapters')->cascadeOnDelete();
            $table->unsignedSmallInteger('verse_number');
            $table->foreignId('translation_id')->constrained('translations')->cascadeOnDelete();
            $table->text('text');
            $table->timestamps();

            $table->unique(['chapter_id', 'verse_number', 'translation_id'], 'bible_verses_unique');
        });

        // Extend reading_blocks with normalized verse range fields
        Schema::table('reading_blocks', function (Blueprint $table) {
            $table->foreignId('start_book_id')->nullable()->after('passage_end')
                ->constrained('biblical_books')->nullOnDelete();
            $table->unsignedSmallInteger('start_chapter')->nullable()->after('start_book_id');
            $table->unsignedSmallInteger('start_verse')->nullable()->after('start_chapter');
            $table->foreignId('end_book_id')->nullable()->after('start_verse')
                ->constrained('biblical_books')->nullOnDelete();
            $table->unsignedSmallInteger('end_chapter')->nullable()->after('end_book_id');
            $table->unsignedSmallInteger('end_verse')->nullable()->after('end_chapter');
        });
    }

    public function down(): void
    {
        Schema::table('reading_blocks', function (Blueprint $table) {
            $table->dropForeign(['start_book_id']);
            $table->dropForeign(['end_book_id']);
            $table->dropColumns(['start_book_id', 'start_chapter', 'start_verse', 'end_book_id', 'end_chapter', 'end_verse']);
        });

        Schema::dropIfExists('bible_verses');
        Schema::dropIfExists('bible_chapters');

        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumns(['source_url', 'source_file_hash', 'imported_at', 'license_label']);
        });

        Schema::table('biblical_books', function (Blueprint $table) {
            $table->dropColumns(['name_es', 'name_en', 'chapter_count']);
        });
    }
};
