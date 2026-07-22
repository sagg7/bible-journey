<?php

namespace App\Console\Commands;

use App\Models\ChronologicalReadingSet;
use App\Models\StreamPlanEdge;
use App\Models\StreamPlanNode;
use App\Services\Harmonization\HarmonizationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeStreamPlanSequence extends Command
{
    use \App\Console\Commands\Concerns\GuardsPublishedPlans;

    protected $signature = 'stream-plans:normalize-sequence {planId} {--dry-run} {--force-published}';

    protected $description = 'Normalize published stream rank for linked Psalms and NT letters/missions';

    public function handle(): int
    {
        $planId = (int) $this->argument('planId');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->assertPlanIsMutable($planId, (bool) $this->option('force-published'))) {
            return self::FAILURE;
        }

        $nodes = StreamPlanNode::where('plan_id', $planId)
            ->with('crs.blocks')
            ->orderBy('rank')
            ->get();

        if ($nodes->isEmpty()) {
            $this->error("No nodes found for plan_id={$planId}");
            return self::FAILURE;
        }

        $order = $nodes->pluck('crs_id')->all();
        $nodesByCrs = $nodes->keyBy('crs_id');
        $sourceToCrs = $nodes->mapWithKeys(fn ($node) => [$node->crs->source_map => $node->crs_id])->all();

        $order = HarmonizationResolver::normalizeChronologicalReadingOrder($order, $sourceToCrs);

        $originalSet = collect($nodes->pluck('crs_id')->all())->sort()->values()->all();
        $newSet = collect($order)->sort()->values()->all();
        if ($originalSet !== $newSet) {
            $this->error('Integrity check failed; node set changed. Aborting.');
            return self::FAILURE;
        }

        $emptyNtMarkerIds = $nodes
            ->filter(fn ($node) => str_starts_with($node->crs->source_map, 'CRS-NT-') && $node->crs->blocks->isEmpty())
            ->pluck('crs_id')
            ->all();

        $paulCrsIds = $nodes
            ->filter(fn ($node) => str_starts_with($node->crs->source_map, 'CRS-PAUL-'))
            ->pluck('crs_id')
            ->all();

        $letterCrsIds = $nodes
            ->filter(fn ($node) => str_starts_with($node->crs->source_map, 'CRS-GLET-') || str_starts_with($node->crs->source_map, 'CRS-REV-'))
            ->pluck('crs_id')
            ->all();

        $ntLetterCrsIds = $nodes
            ->filter(fn ($node) => in_array($node->crs->source_map, [
                'CRS-NT-013',
                'CRS-NT-020',
                'CRS-NT-021',
                'CRS-NT-024',
                'CRS-NT-026',
                'CRS-NT-028',
                'CRS-NT-034',
                'CRS-NT-035',
                'CRS-NT-036',
                'CRS-NT-037',
                'CRS-NT-038',
                'CRS-NT-039',
                'CRS-NT-040',
            ], true))
            ->pluck('crs_id')
            ->all();

        $gapMatthewIds = $nodes
            ->filter(fn ($node) => str_starts_with($node->crs->source_map, 'CRS-GAP-MAT-'))
            ->pluck('crs_id')
            ->all();

        $this->info('Empty NT markers to remove from main navigation: ' . count($emptyNtMarkerIds));
        $this->info('Pauline detailed readings to classify with letters/mission: ' . count($paulCrsIds));

        $this->newLine();
        $this->info('--- NT/Psalms sample order after normalization ---');
        $sample = 0;
        foreach ($order as $i => $crsId) {
            $source = $nodesByCrs[$crsId]->crs->source_map;
            if (
                str_starts_with($source, 'CRS-PSA-')
                || str_starts_with($source, 'CRS-ACT-')
                || str_starts_with($source, 'CRS-PAUL-')
                || str_starts_with($source, 'CRS-GLET-')
                || str_starts_with($source, 'CRS-REV-')
            ) {
                $this->line(str_pad((string) ($i + 1), 4, ' ', STR_PAD_LEFT) . " {$source}");
                if (++$sample >= 45) {
                    break;
                }
            }
        }

        if ($dryRun) {
            $this->info('DRY RUN; no database changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($order, $nodesByCrs, $emptyNtMarkerIds, $paulCrsIds, $letterCrsIds, $ntLetterCrsIds, $gapMatthewIds, $planId) {
            foreach ($order as $i => $crsId) {
                $node = $nodesByCrs[$crsId];
                $newRank = $i + 1;
                if ($node->rank !== $newRank) {
                    StreamPlanNode::where('id', $node->id)->update(['rank' => $newRank]);
                }
            }

            if (! empty($emptyNtMarkerIds)) {
                ChronologicalReadingSet::whereIn('id', $emptyNtMarkerIds)->update([
                    'is_main_stream_node' => false,
                    'complete_mode_behavior' => 'context_only',
                    'display_mode' => null,
                ]);

                StreamPlanNode::where('plan_id', $planId)
                    ->whereIn('crs_id', $emptyNtMarkerIds)
                    ->update([
                        'is_main_stream_node' => false,
                        'required_state' => 'none',
                        'display_mode' => 'full',
                    ]);
            }

            if (! empty($paulCrsIds)) {
                ChronologicalReadingSet::whereIn('id', $paulCrsIds)->update([
                    'is_main_stream_node' => true,
                    'user_facing_era' => 'Las cartas y la expansion de la iglesia',
                    'user_facing_era_sort' => 220,
                ]);

                StreamPlanNode::where('plan_id', $planId)
                    ->whereIn('crs_id', $paulCrsIds)
                    ->update([
                        'is_main_stream_node' => true,
                        'user_facing_era' => 'Las cartas y la expansion de la iglesia',
                        'user_facing_era_sort' => 220,
                    ]);
            }

            if (! empty($ntLetterCrsIds)) {
                ChronologicalReadingSet::whereIn('id', $ntLetterCrsIds)->update([
                    'user_facing_era' => 'Las cartas y la expansion de la iglesia',
                    'user_facing_era_sort' => 220,
                ]);

                StreamPlanNode::where('plan_id', $planId)
                    ->whereIn('crs_id', $ntLetterCrsIds)
                    ->update([
                        'user_facing_era' => 'Las cartas y la expansion de la iglesia',
                        'user_facing_era_sort' => 220,
                    ]);
            }

            if (! empty($letterCrsIds)) {
                ChronologicalReadingSet::whereIn('id', $letterCrsIds)->update([
                    'is_main_stream_node' => true,
                ]);

                StreamPlanNode::where('plan_id', $planId)
                    ->whereIn('crs_id', $letterCrsIds)
                    ->update([
                        'is_main_stream_node' => true,
                    ]);
            }

            if (! empty($gapMatthewIds)) {
                ChronologicalReadingSet::whereIn('id', $gapMatthewIds)->update([
                    'is_main_stream_node' => false,
                    'complete_mode_behavior' => 'context_only',
                ]);

                StreamPlanNode::where('plan_id', $planId)
                    ->whereIn('crs_id', $gapMatthewIds)
                    ->update([
                        'is_main_stream_node' => false,
                        'required_state' => 'none',
                    ]);
            }

            StreamPlanEdge::where('plan_id', $planId)
                ->where('evidence_note', 'like', 'Inserted by stream-plans:normalize-sequence%')
                ->delete();

            $mainNodes = StreamPlanNode::where('plan_id', $planId)
                ->where('is_main_stream_node', true)
                ->orderBy('rank')
                ->get(['id', 'rank']);

            for ($i = 0; $i < $mainNodes->count() - 1; $i++) {
                $from = $mainNodes[$i];
                $to = $mainNodes[$i + 1];

                $hasOutgoing = StreamPlanEdge::where('plan_id', $planId)
                    ->where('from_node_id', $from->id)
                    ->exists();

                if ($hasOutgoing) {
                    continue;
                }

                StreamPlanEdge::firstOrCreate(
                    [
                        'plan_id' => $planId,
                        'from_node_id' => $from->id,
                        'to_node_id' => $to->id,
                    ],
                    [
                        'edge_type' => 'SEQUENTIAL_DIRECT',
                        'score' => 0.9000,
                        'priority' => 1,
                        'evidence_note' => 'Inserted by stream-plans:normalize-sequence to preserve rank-based main navigation.',
                    ]
                );
            }
        });

        $this->info("Done. Normalized plan {$planId}.");
        return self::SUCCESS;
    }
}
