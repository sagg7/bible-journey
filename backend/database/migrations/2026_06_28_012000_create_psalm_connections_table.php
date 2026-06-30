<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexión de un Salmo con su momento histórico probable.
 * El diferenciador del producto: cada conexión lleva nivel de certeza y nota de advertencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psalm_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_event_id')->constrained()->cascadeOnDelete();
            $table->string('psalm_reference');              // "Salmo 34"
            $table->foreignId('passage_id')->nullable()->constrained()->nullOnDelete(); // pasaje del Salmo
            $table->string('certainty_level')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psalm_connections');
    }
};
