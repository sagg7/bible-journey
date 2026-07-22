<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chronological_reading_sets', function (Blueprint $table) {
            if (! Schema::hasColumn('chronological_reading_sets', 'approximate_date_start')) {
                $table->string('approximate_date_start')->nullable()->after('era_slug')->index();
            }

            if (! Schema::hasColumn('chronological_reading_sets', 'approximate_date_end')) {
                $table->string('approximate_date_end')->nullable()->after('approximate_date_start');
            }

            if (! Schema::hasColumn('chronological_reading_sets', 'date_confidence')) {
                $table->string('date_confidence')->nullable()->after('approximate_date_end');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'approximate_date_start',
            'approximate_date_end',
            'date_confidence',
        ], fn (string $column): bool => Schema::hasColumn('chronological_reading_sets', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('chronological_reading_sets', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
