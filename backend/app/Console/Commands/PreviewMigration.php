<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreviewMigration extends Command
{
    protected $signature = 'stream-plans:preview-migration
                            {from_plan_id : Source plan ID}
                            {to_plan_id   : Target plan ID}
                            {--fixtures   : Create synthetic fixtures if no real progress exists}';

    protected $description = 'Preview user progress migration from one stream plan to another';

    public function handle(): int
    {
        $fromId = (int) $this->argument('from_plan_id');
        $toId   = (int) $this->argument('to_plan_id');

        $fromPlan = DB::table('stream_plans')->where('id', $fromId)->first();
        $toPlan   = DB::table('stream_plans')->where('id', $toId)->first();

        if (! $fromPlan || ! $toPlan) {
            $this->error('One or both plans not found.');
            return 1;
        }

        $this->info("Progress migration preview: Plan #{$fromId} → Plan #{$toId}");

        // ── Check for real progress records ──────────────────────────────────
        $realProgress = DB::table('user_canonical_progress')
            ->where('plan_id', $fromId)->count();

        if ($realProgress === 0) {
            $this->warn("No real user progress found for Plan #{$fromId}.");

            if ($this->option('fixtures') || $this->confirm('Create synthetic fixtures for testing?', true)) {
                $this->createSyntheticFixtures($fromId);
                $realProgress = DB::table('user_canonical_progress')
                    ->where('plan_id', $fromId)->count();
                $this->info("Created {$realProgress} synthetic progress records.");
            } else {
                $this->info("Skipping migration preview (no progress to migrate).");
                return 0;
            }
        }

        // ── Build block identity map for Plan 9 ──────────────────────────────
        // Identity: start_book_id + start_chapter + coalesce(end_chapter, start_chapter)
        $plan9Blocks = DB::table('reading_blocks as rb')
            ->join('stream_plan_nodes as spn', function ($j) use ($toId) {
                $j->on('spn.crs_id', '=', 'rb.crs_id')->where('spn.plan_id', '=', $toId);
            })
            ->whereNotNull('rb.start_book_id')
            ->select(
                'rb.id as block_id',
                'spn.id as node_id',
                'rb.start_book_id',
                'rb.start_chapter',
                DB::raw('COALESCE(rb.end_book_id, rb.start_book_id) as end_book_id'),
                DB::raw('COALESCE(rb.end_chapter, rb.start_chapter) as end_chapter')
            )
            ->get()
            ->keyBy(fn($b) => "{$b->start_book_id}:{$b->start_chapter}:{$b->end_book_id}:{$b->end_chapter}");

        // ── Map Plan 8 progress records ───────────────────────────────────────
        $progress8 = DB::table('user_canonical_progress as ucp')
            ->join('reading_blocks as rb', 'rb.id', '=', 'ucp.block_id')
            ->where('ucp.plan_id', $fromId)
            ->select(
                'ucp.id as progress_id',
                'ucp.user_id',
                'ucp.block_id as old_block_id',
                'ucp.status',
                'rb.start_book_id',
                'rb.start_chapter',
                DB::raw('COALESCE(rb.end_book_id, rb.start_book_id) as end_book_id'),
                DB::raw('COALESCE(rb.end_chapter, rb.start_chapter) as end_chapter')
            )
            ->get();

        $mappedSame   = 0;
        $mappedNew    = 0;
        $unmapped     = 0;
        $duplicates   = 0;
        $unmappedDetails = [];

        foreach ($progress8 as $rec) {
            $key = "{$rec->start_book_id}:{$rec->start_chapter}:{$rec->end_book_id}:{$rec->end_chapter}";

            if (! isset($plan9Blocks[$key])) {
                $unmapped++;
                $unmappedDetails[] = [
                    'progress_id'  => $rec->progress_id,
                    'old_block_id' => $rec->old_block_id,
                    'identity'     => $key,
                    'status'       => $rec->status,
                ];
                continue;
            }

            $target = $plan9Blocks[$key];

            if ($target->block_id === $rec->old_block_id) {
                $mappedSame++;
            } else {
                $mappedNew++;
            }
        }

        // ── Check for duplicates in target (multiple Plan 8 blocks → same Plan 9 block) ─
        $seen = [];
        foreach ($progress8 as $rec) {
            $key = "{$rec->start_book_id}:{$rec->start_chapter}:{$rec->end_book_id}:{$rec->end_chapter}";
            if (isset($plan9Blocks[$key])) {
                $targetBlockId = $plan9Blocks[$key]->block_id;
                if (isset($seen[$rec->user_id][$targetBlockId])) {
                    $duplicates++;
                }
                $seen[$rec->user_id][$targetBlockId] = true;
            }
        }

        // ── Print report ──────────────────────────────────────────────────────
        $total = $progress8->count();

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['progress_records_total',    $total],
                ['mapped_without_change',     $mappedSame],
                ['mapped_to_new_node',        $mappedNew],
                ['unmapped_progress_records', $unmapped],
                ['duplicate_progress_matches',$duplicates],
            ]
        );

        if ($unmapped > 0) {
            $this->warn("\nUnmapped records:");
            foreach ($unmappedDetails as $d) {
                $this->line("  block #{$d['old_block_id']} identity={$d['identity']} status={$d['status']}");
            }
        }

        $exitCode = ($unmapped === 0 && $duplicates === 0) ? 0 : 1;

        if ($exitCode === 0) {
            $this->newLine();
            $this->info('Migration gate: PASS — all progress records map cleanly to Plan ' . $toId);
        } else {
            $this->newLine();
            $this->error('Migration gate: FAIL — unmapped or duplicate records must be resolved before publishing');
        }

        return $exitCode;
    }

    private function createSyntheticFixtures(int $planId): void
    {
        // Get a sample of Plan 8 blocks to create synthetic progress
        $sampleBlocks = DB::table('reading_blocks as rb')
            ->join('stream_plan_nodes as spn', function ($j) use ($planId) {
                $j->on('spn.crs_id', '=', 'rb.crs_id')->where('spn.plan_id', '=', $planId);
            })
            ->where('rb.required_in_complete_mode', 1)
            ->select('rb.id as block_id')
            ->limit(30)
            ->get();

        // Use user_id=1 (synthetic test user)
        $rows = $sampleBlocks->map(fn($b) => [
            'user_id'    => 1,
            'block_id'   => $b->block_id,
            'plan_id'    => $planId,
            'plan_version'=> null,
            'status'     => 'completed',
            'started_at' => now()->subDays(10),
            'completed_at'=> now()->subDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('user_canonical_progress')->insert($rows);
    }
}
