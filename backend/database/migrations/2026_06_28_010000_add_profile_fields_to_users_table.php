<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->string('subscription_status')->default('free')->after('is_admin');
            $table->string('preferred_language', 5)->default('es')->after('subscription_status');
            $table->string('reading_level')->nullable()->after('preferred_language');
            $table->json('reminder_settings')->nullable()->after('reading_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin', 'subscription_status', 'preferred_language',
                'reading_level', 'reminder_settings',
            ]);
        });
    }
};
