<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('has_test_access')
                ->constrained('institutions')->nullOnDelete();
            $table->boolean('is_institution_admin')->default(false)->after('institution_id');
            $table->string('revenuecat_customer_id')->nullable()->after('is_institution_admin');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn(['is_institution_admin', 'revenuecat_customer_id', 'subscription_expires_at']);
        });
    }
};
