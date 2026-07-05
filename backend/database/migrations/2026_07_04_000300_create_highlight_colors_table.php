<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlight_colors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('color_hex', 7);
            $table->string('label')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'color_hex']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlight_colors');
    }
};
