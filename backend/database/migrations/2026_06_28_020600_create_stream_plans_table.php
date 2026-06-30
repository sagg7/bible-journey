<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_plans', function (Blueprint $table) {
            $table->id();

            $table->string('profile_id', 64)->default('cautious_default')->index();
            $table->string('ledger_snapshot_id', 64)->nullable()->comment('Hash or version of the ledger used to compile this plan');
            $table->string('locale', 8)->default('es');

            $table->enum('publication_status', ['draft', 'published', 'archived', 'invalid'])->default('draft')->index();

            // Deterministic hash of the full plan for change detection
            $table->string('validation_hash', 64)->nullable();

            $table->unsignedInteger('node_count')->default(0);
            $table->unsignedInteger('edge_count')->default(0);

            // Compilation metadata
            $table->json('compilation_warnings')->nullable();
            $table->json('compilation_errors')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_plans');
    }
};
