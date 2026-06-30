<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_event_id')->constrained()->cascadeOnDelete();
            $table->string('type');                         // historico|cultural|geografico|literario|politico
            $table->string('certainty_level')->nullable();
            $table->json('sources')->nullable();            // [{ "title": "...", "ref": "..." }]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_notes');
    }
};
