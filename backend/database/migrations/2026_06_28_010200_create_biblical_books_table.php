<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblical_books', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();              // "1-samuel", "salmos"
            $table->string('osis_code')->nullable();       // "1Sam", "Ps"
            $table->string('testament', 2);                // OT, NT
            $table->string('genre')->nullable();           // narrativa, poesía, profecía...
            $table->string('traditional_author')->nullable();
            $table->unsignedInteger('canonical_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblical_books');
    }
};
