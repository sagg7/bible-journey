<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_decisions', function (Blueprint $table) {
            $table->id();

            $table->string('source_key', 64)->unique()->comment('e.g. DEC-DAV-001');
            $table->string('topic', 256);

            $table->enum('status', ['open', 'resolved', 'deferred', 'wont_resolve'])->default('open');

            $table->enum('impact_scope', ['single_crs', 'multiple_crs', 'era', 'global'])->default('single_crs');

            // What the app does while this is unresolved
            $table->text('interim_policy')->nullable()->comment('How the app handles this while unresolved');

            $table->string('owner', 128)->nullable();

            // Linked CRS (nullable JSON array of CRS source_maps)
            $table->json('affected_crs')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_decisions');
    }
};
