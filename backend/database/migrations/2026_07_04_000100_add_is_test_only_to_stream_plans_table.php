<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stream_plans', function (Blueprint $table) {
            $table->boolean('is_test_only')->default(false)->after('publication_status');
        });
    }

    public function down(): void
    {
        Schema::table('stream_plans', function (Blueprint $table) {
            $table->dropColumn('is_test_only');
        });
    }
};
