<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto completo de un pasaje en una traducción concreta.
 * Solo se pobla para traducciones de dominio público (is_public_domain / can_display_full_text).
 * Las protegidas (NVI/NIV/RVR60) no tienen filas hasta obtener licencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passage_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('translation_id')->constrained()->cascadeOnDelete();
            $table->longText('content');                   // texto renderizado del pasaje
            $table->json('verses')->nullable();            // opcional: [{ "v": 1, "t": "..." }, ...]
            $table->timestamps();

            $table->unique(['passage_id', 'translation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passage_texts');
    }
};
