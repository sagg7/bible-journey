<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();              // "samuel-unge-a-david"
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('era')->nullable();
            $table->string('approximate_date_start')->nullable(); // "c. 1025 a.C." (texto: las fechas a.C. no caben en date)
            $table->string('approximate_date_end')->nullable();
            $table->string('date_confidence')->nullable();        // certainty_level para la fecha
            $table->string('certainty_level')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_events');
    }
};
