<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_plan_edges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')->constrained('stream_plans')->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('stream_plan_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('stream_plan_nodes')->cascadeOnDelete();

            $table->enum('edge_type', [
                'SEQUENTIAL_DIRECT',
                'PARALLEL_ACCOUNT',
                'COMPLEMENTARY_ACCOUNT',
                'PROPHETIC_CONTEXT',
                'POETIC_CONNECTION',
                'EPISTOLARY_CONTEXT',
                'CANONICAL_FALLBACK',
                'LITERARY_SEQUENCE',
                'INTERTEXTUAL_REFERENCE',
            ])->default('SEQUENTIAL_DIRECT');

            // Computed score from HarmonizationResolver (0.0–1.0)
            $table->decimal('score', 5, 4)->default(0);

            // Priority within a fan-out from one node
            $table->unsignedTinyInteger('priority')->default(1);

            $table->text('evidence_note')->nullable();

            $table->timestamps();

            $table->unique(['plan_id', 'from_node_id', 'to_node_id'], 'unique_plan_edge');
            $table->index(['plan_id', 'from_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_plan_edges');
    }
};
