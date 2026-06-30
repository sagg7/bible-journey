<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_note_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('context_note_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->longText('content');
            $table->string('review_status')->default('draft');
            $table->timestamps();

            $table->unique(['context_note_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_note_translations');
    }
};
