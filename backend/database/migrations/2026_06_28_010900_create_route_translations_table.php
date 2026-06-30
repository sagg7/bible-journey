<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('review_status')->default('draft');
            $table->timestamps();

            $table->unique(['route_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_translations');
    }
};
