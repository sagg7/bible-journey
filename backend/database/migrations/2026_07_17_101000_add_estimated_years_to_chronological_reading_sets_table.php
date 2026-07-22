<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            if (! Schema::hasColumn('chronological_reading_sets', 'approximate_year_start')) {
                $table->integer('approximate_year_start')->nullable()->after('date_confidence')->index();
            }

            if (! Schema::hasColumn('chronological_reading_sets', 'approximate_year_end')) {
                $table->integer('approximate_year_end')->nullable()->after('approximate_year_start');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'approximate_year_start',
            'approximate_year_end',
        ], fn (string $column): bool => Schema::hasColumn('chronological_reading_sets', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('chronological_reading_sets', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
