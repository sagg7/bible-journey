<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parallel_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_block_id')->constrained('reading_blocks')->cascadeOnDelete();
            $table->foreignId('target_block_id')->constrained('reading_blocks')->cascadeOnDelete();

            $table->enum('relation_type', [
                'SEQUENTIAL_DIRECT',
                'PARALLEL_ACCOUNT',
                'COMPLEMENTARY_ACCOUNT',
                'PROPHETIC_CONTEXT',
                'POETIC_CONNECTION',
                'EPISTOLARY_CONTEXT',
                'CANONICAL_FALLBACK',
                'LITERARY_SEQUENCE',
                'INTERTEXTUAL_REFERENCE',
            ])->default('PARALLEL_ACCOUNT');

            $table->enum('confidence', ['alta', 'probable', 'debatida', 'tradicion_popular', 'especulativa'])->default('probable');

            $table->text('evidence_note')->nullable();

            // Group for Compare Accounts view
            $table->foreignId('compare_group_id')->nullable()->constrained('compare_groups')->nullOnDelete();

            $table->boolean('approved')->default(false);

            $table->timestamps();

            // No duplicate links
            $table->unique(['source_block_id', 'target_block_id', 'relation_type'], 'unique_parallel_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parallel_links');
    }
};
