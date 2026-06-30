<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('review_status')->default('draft');
            $table->timestamps();

            $table->unique(['location_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_translations');
    }
};
