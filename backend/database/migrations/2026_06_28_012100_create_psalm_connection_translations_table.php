<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psalm_connection_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psalm_connection_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('reasoning');                      // evidencia textual de la conexión
            $table->text('warning_note')->nullable();       // advertencia si la asociación es debatida
            $table->string('review_status')->default('draft');
            $table->timestamps();

            $table->unique(['psalm_connection_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psalm_connection_translations');
    }
};
