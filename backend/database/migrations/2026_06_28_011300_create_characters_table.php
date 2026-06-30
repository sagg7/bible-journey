<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();              // "david", "saul"
            $table->foreignId('first_appearance_event_id')->nullable()
                ->constrained('historical_events')->nullOnDelete();
            $table->foreignId('death_event_id')->nullable()
                ->constrained('historical_events')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
