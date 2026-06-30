<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_snapshots', function (Blueprint $table) {
            $table->id();

            $table->string('snapshot_id', 64)->unique()->comment('Hash of the source XLSX file');
            $table->string('source_file', 256)->nullable();
            $table->string('ledger_version', 32)->nullable()->comment('e.g. 1.0');

            // Import counts
            $table->unsignedInteger('crs_count')->default(0);
            $table->unsignedInteger('block_count')->default(0);
            $table->unsignedInteger('link_count')->default(0);
            $table->unsignedInteger('decision_count')->default(0);

            // Which pilots were imported
            $table->json('imported_pilots')->nullable()->comment('e.g. ["DAV","HZK","GOS"]');

            $table->enum('status', ['pending', 'imported', 'failed', 'superseded'])->default('pending');
            $table->text('import_log')->nullable();

            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_snapshots');
    }
};
