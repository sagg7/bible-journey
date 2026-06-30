<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clones a stream plan's nodes and edges into a new draft plan.
 *
 * Used to create Plan 9.1 from Plan 9 without rerunning HarmonizationResolver.
 * The coverage paths are NOT cloned — run coverage:build on the new plan after.
 */
class CloneStreamPlan extends Command
{
    protected $signature = 'stream-plans:clone
                            {source_plan_id : ID of the plan to clone}
                            {--plan-version= : Version string for the new plan (e.g. "9.1")}
                            {--purpose= : Human-readable reason for the clone}';

    protected $description = 'Clone a stream plan\'s nodes and edges into a new draft plan';

    public function handle(): int
    {
        $sourceId = (int) $this->argument('source_plan_id');
        $version  = $this->option('plan-version') ?? null;
        $purpose  = $this->option('purpose') ?? null;

        $source = DB::table('stream_plans')->where('id', $sourceId)->first();
        if (! $source) {
            $this->error("Source plan #{$sourceId} not found.");
            return 1;
        }

        $this->info("Cloning Plan #{$sourceId}…");

        // ── 1. Create new stream plan (draft) ────────────────────────────────────
        $newPlanId = DB::table('stream_plans')->insertGetId([
            'parent_plan_id'   => $sourceId,
            'profile_id'       => $source->profile_id,
            'ledger_snapshot_id' => $source->ledger_snapshot_id,
            'locale'           => $source->locale,
            'publication_status' => 'draft',
            'version'          => $version,
            'purpose'          => $purpose,
            'node_count'       => $source->node_count,
            'edge_count'       => $source->edge_count,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->line("  Created Plan #{$newPlanId} (draft).");

        // ── 2. Clone nodes — build old_id → new_id map for edge translation ──────
        $oldNodes = DB::table('stream_plan_nodes')
            ->where('plan_id', $sourceId)
            ->orderBy('rank')
            ->get();

        $nodeMap = [];

        foreach ($oldNodes as $node) {
            $newNodeId = DB::table('stream_plan_nodes')->insertGetId([
                'plan_id'              => $newPlanId,
                'rank'                 => $node->rank,
                'crs_id'               => $node->crs_id,
                'display_mode'         => $node->display_mode,
                'required_state'       => $node->required_state,
                'stream_role'          => $node->stream_role,
                'user_facing_era'      => $node->user_facing_era,
                'user_facing_era_sort' => $node->user_facing_era_sort,
                'is_main_stream_node'  => $node->is_main_stream_node,
                'explanation_es'       => $node->explanation_es,
                'explanation_en'       => $node->explanation_en ?? null,
                'compare_group_id'     => $node->compare_group_id,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
            $nodeMap[$node->id] = $newNodeId;
        }

        $nodeCount = count($nodeMap);
        $this->line("  Cloned {$nodeCount} nodes.");

        // ── 3. Clone edges using the node ID mapping ─────────────────────────────
        $oldEdges = DB::table('stream_plan_edges')
            ->where('plan_id', $sourceId)
            ->get();

        $edgeRows  = [];
        $skipped   = 0;

        foreach ($oldEdges as $edge) {
            if (! isset($nodeMap[$edge->from_node_id], $nodeMap[$edge->to_node_id])) {
                $skipped++;
                continue;
            }
            $edgeRows[] = [
                'plan_id'      => $newPlanId,
                'from_node_id' => $nodeMap[$edge->from_node_id],
                'to_node_id'   => $nodeMap[$edge->to_node_id],
                'edge_type'    => $edge->edge_type,
                'score'        => $edge->score,
                'priority'     => $edge->priority,
                'evidence_note'=> $edge->evidence_note,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        foreach (array_chunk($edgeRows, 200) as $chunk) {
            DB::table('stream_plan_edges')->insert($chunk);
        }

        $edgeCount = count($edgeRows);
        $this->line("  Cloned {$edgeCount} edges" . ($skipped ? " ({$skipped} orphan edges skipped)" : '.'));

        // ── 4. Update node_count / edge_count on new plan ────────────────────────
        DB::table('stream_plans')
            ->where('id', $newPlanId)
            ->update([
                'node_count' => $nodeCount,
                'edge_count' => $edgeCount,
                'updated_at' => now(),
            ]);

        $this->newLine();
        $this->info("Plan #{$newPlanId} created (draft).");
        $this->line("  Source:   Plan #{$sourceId}");
        $this->line("  Version:  " . ($version ?? '—'));
        $this->line("  Purpose:  " . ($purpose ?? '—'));
        $this->line("  Nodes:    {$nodeCount}");
        $this->line("  Edges:    {$edgeCount}");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("  php artisan coverage:build {$newPlanId}");
        $this->line("  php artisan stream-plans:verify {$newPlanId}");

        return 0;
    }
}
