<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblical_book_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');                        // "1 Samuel" / "1 Samuel"
            $table->text('notes')->nullable();
            $table->string('review_status')->default('draft');
            $table->timestamps();

            $table->unique(['biblical_book_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_translations');
    }
};
