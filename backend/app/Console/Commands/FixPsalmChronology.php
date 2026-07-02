<?php

namespace App\Console\Commands;

use App\Models\ParallelLink;
use App\Models\StreamPlanNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time surgical fix for Plan 10: repositions Psalm/Chronicles "literary
 * window" CRS nodes immediately after their approved parallel_links target
 * (the historical event they reference), instead of leaving them grouped at
 * the tail of their sort_key bucket. Does not touch any node that has no
 * approved parallel_link; only the explicitly linked nodes move.
 *
 * Safe to re-run: idempotent given the same approved links and starting order.
 */
class FixPsalmChronology extends Command
{
    protected $signature = 'stream-plans:fix-psalm-chronology {planId} {--dry-run}';

    protected $description = 'Reposition linked literary-window CRS nodes next to their historical anchor in a published plan';

    public function handle(): int
    {
        $planId = (int) $this->argument('planId');
        $dryRun = (bool) $this->option('dry-run');

        $links = ParallelLink::where('approved', true)
            ->with(['sourceBlock.crs', 'targetBlock.crs'])
            ->get();

        $pairs = [];
        foreach ($links as $link) {
            $srcCrsId = $link->sourceBlock?->crs_id;
            $tgtCrsId = $link->targetBlock?->crs_id;
            if (! $srcCrsId || ! $tgtCrsId || $srcCrsId === $tgtCrsId) {
                continue;
            }

            $pairs[$srcCrsId] = $tgtCrsId;
        }

        if (empty($pairs)) {
            $this->warn('No approved parallel_links found; nothing to do.');
            return self::SUCCESS;
        }

        $this->info('Found ' . count($pairs) . ' approved source->target pairs.');

        $nodes = StreamPlanNode::where('plan_id', $planId)
            ->orderBy('rank')
            ->get(['id', 'crs_id', 'rank']);

        if ($nodes->isEmpty()) {
            $this->error("No nodes found for plan_id={$planId}");
            return self::FAILURE;
        }

        $idByCrs = $nodes->keyBy('crs_id');
        $order = $nodes->pluck('crs_id')->toArray();

        $sources = array_keys($pairs);
        $order = array_values(array_diff($order, $sources));

        $pending = $pairs;
        $insertedAfter = [];
        $guard = 0;
        while (! empty($pending) && $guard++ < 50) {
            $progressed = false;
            foreach ($pending as $srcCrsId => $tgtCrsId) {
                $pos = array_search($tgtCrsId, $order, true);
                if ($pos === false) {
                    continue;
                }

                $offset = $insertedAfter[$tgtCrsId] ?? 0;
                array_splice($order, $pos + 1 + $offset, 0, [$srcCrsId]);
                $insertedAfter[$tgtCrsId] = $offset + 1;
                unset($pending[$srcCrsId]);
                $progressed = true;
            }

            if (! $progressed) {
                break;
            }
        }

        if (! empty($pending)) {
            $this->warn('Could not resolve target position for: ' . implode(', ', array_keys($pending)) . '; appending at end.');
            foreach (array_keys($pending) as $srcCrsId) {
                $order[] = $srcCrsId;
            }
        }

        $originalSet = $nodes->pluck('crs_id')->sort()->values()->all();
        $newSet = collect($order)->sort()->values()->all();
        if ($originalSet !== $newSet) {
            $this->error('Integrity check failed; node set changed. Aborting without writing.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('--- Sample moves (rank before -> after) ---');
        $beforeRank = $nodes->pluck('rank', 'crs_id');
        $sampleCount = 0;
        foreach ($order as $i => $crsId) {
            $newRank = $i + 1;
            if (in_array($crsId, $sources) && $sampleCount < 15) {
                $node = $idByCrs[$crsId];
                $sourceMap = $node->crs?->source_map ?? "crs#{$crsId}";
                $this->line("  {$sourceMap}: rank {$beforeRank[$crsId]} -> {$newRank}");
                $sampleCount++;
            }
        }

        if ($dryRun) {
            $this->info('DRY RUN; no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($order, $idByCrs) {
            foreach ($order as $i => $crsId) {
                $node = $idByCrs[$crsId];
                $newRank = $i + 1;
                if ($node->rank !== $newRank) {
                    StreamPlanNode::where('id', $node->id)->update(['rank' => $newRank]);
                }
            }
        });

        $this->newLine();
        $this->info('Done. Reordered ' . count($pairs) . " nodes in plan {$planId}.");
        return self::SUCCESS;
    }
}
