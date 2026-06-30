<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_event')->nullable();
            $table->string('status_at_event')->nullable();  // vivo|muerto|activo|fuera_de_escena
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['historical_event_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_characters');
    }
};
