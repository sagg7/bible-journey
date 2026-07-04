<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->boolean('is_premium')->default(true)->after('editorial_note');
        });
    }

    public function down(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            $table->dropColumn('is_premium');
        });
    }
};
