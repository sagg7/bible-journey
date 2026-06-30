<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('passage_id')->constrained()->cascadeOnDelete();
            $table->string('relationship_type')->nullable(); // primary|parallel|background
            $table->string('certainty_level')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['historical_event_id', 'passage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_passages');
    }
};
