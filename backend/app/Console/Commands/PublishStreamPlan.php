<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublishStreamPlan extends Command
{
    protected $signature = 'stream-plans:publish
                            {plan_id : Stream plan ID to publish}
                            {--confirm : Required to proceed (prevents accidental activation)}
                            {--skip-migration : Skip progress migration check (use only when no users exist)}';

    protected $description = 'Safely publish a stream plan with full gate verification and audit trail';

    public function handle(): int
    {
        $planId = (int) $this->argument('plan_id');

        if (! $this->option('confirm')) {
            $this->error('Requires --confirm flag to prevent accidental activation.');
            $this->line('Usage: php artisan stream-plans:publish ' . $planId . ' --confirm');
            return 1;
        }

        $plan = DB::table('stream_plans')->where('id', $planId)->first();
        if (! $plan) {
            $this->error("Plan #{$planId} not found.");
            return 1;
        }

        if ($plan->publication_status === 'published') {
            $this->error("Plan #{$planId} is already published.");
            return 1;
        }

        $this->info("Publishing Stream Plan #{$planId}…");
        $this->newLine();

        // ── Step 1: Run verification ───────────────────────────────────────────
        $this->line('Step 1/7 — Running stream-plans:verify…');
        $verifyExit = Artisan::call('stream-plans:verify', ['plan_id' => $planId]);
        $this->line(Artisan::output());

        if ($verifyExit !== 0) {
            $this->error('Publication aborted: verification gate FAILED.');
            $this->line('Fix all blocking issues and re-run.');
            return 1;
        }
        $this->info('  ✓ Verification passed.');

        // ── Step 2: Find current published plan ───────────────────────────────
        $currentPlan = DB::table('stream_plans')
            ->where('publication_status', 'published')
            ->latest('published_at')
            ->first();

        // ── Step 3: Create audit snapshot of current plan ─────────────────────
        $this->line('Step 2/7 — Creating audit snapshot of current plan…');
        $snapshotData = [
            'action'           => 'publish',
            'new_plan_id'      => $planId,
            'previous_plan_id' => $currentPlan?->id,
            'published_by'     => get_current_user() ?: 'cli',
            'timestamp'        => now()->toIso8601String(),
            'verification_report_path' => "reports/stream-plan-{$planId}-verification.json",
        ];

        if ($currentPlan) {
            $snapshotData['previous_plan_snapshot'] = [
                'id'               => $currentPlan->id,
                'publication_status'=> $currentPlan->publication_status,
                'node_count'       => $currentPlan->node_count,
                'edge_count'       => $currentPlan->edge_count,
                'published_at'     => $currentPlan->published_at,
                'validation_hash'  => $currentPlan->validation_hash,
            ];
        }

        $auditPath = "reports/stream-plan-{$planId}-publish-audit.json";
        Storage::makeDirectory('reports');
        Storage::put($auditPath, json_encode($snapshotData, JSON_PRETTY_PRINT));
        $this->info("  ✓ Audit log saved: " . storage_path("app/{$auditPath}"));

        // ── Step 4: Preview migration ─────────────────────────────────────────
        if (! $this->option('skip-migration') && $currentPlan) {
            $this->line("Step 3/7 — Previewing progress migration (Plan #{$currentPlan->id} → #{$planId})…");
            $migExit = Artisan::call('stream-plans:preview-migration', [
                'from_plan_id' => $currentPlan->id,
                'to_plan_id'   => $planId,
            ]);
            $this->line(Artisan::output());

            if ($migExit !== 0) {
                $this->error('Publication aborted: migration gate FAILED — unmapped progress records.');
                return 1;
            }
            $this->info('  ✓ Migration preview passed.');
        } else {
            $this->line('Step 3/7 — Migration check skipped (--skip-migration or no previous plan).');
        }

        // ── Step 5: Publish within a transaction ──────────────────────────────
        $this->line('Step 4/7 — Publishing plan within transaction…');
        try {
            DB::transaction(function () use ($planId, $currentPlan, $auditPath) {
                // Archive the current published plan
                if ($currentPlan) {
                    DB::table('stream_plans')
                        ->where('id', $currentPlan->id)
                        ->update(['publication_status' => 'archived', 'updated_at' => now()]);
                }

                // Publish the new plan
                DB::table('stream_plans')
                    ->where('id', $planId)
                    ->update([
                        'publication_status'       => 'published',
                        'published_at'             => now(),
                        'updated_at'               => now(),
                    ]);
            });
        } catch (\Throwable $e) {
            $this->error('Transaction failed: ' . $e->getMessage());
            $this->line('No changes were committed. Run stream-plans:rollback if needed.');
            return 1;
        }
        $this->info("  ✓ Plan #{$planId} published, Plan #{$currentPlan?->id} archived.");

        // ── Step 6: Invalidate cache ──────────────────────────────────────────
        $this->line('Step 5/7 — Invalidating stream plan cache…');
        Cache::forget('stream_plan_active');
        Cache::forget("stream_plan_{$planId}");
        if ($currentPlan) {
            Cache::forget("stream_plan_{$currentPlan->id}");
        }
        $this->info('  ✓ Cache cleared.');

        // ── Step 7: Rebuild coverage for new plan ────────────────────────────
        $pathsExist = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)->exists();
        if (! $pathsExist) {
            $this->line('Step 6/7 — Building coverage paths for Plan #{$planId}…');
            Artisan::call('coverage:build', ['plan_id' => $planId]);
            $this->info('  ✓ Coverage paths built.');
        } else {
            $this->line('Step 6/7 — Coverage paths already exist for Plan #' . $planId . '.');
        }

        // ── Step 8: Final status ──────────────────────────────────────────────
        $this->line('Step 7/7 — Verifying final state…');
        $final = DB::table('stream_plans')->where('id', $planId)->first();
        $this->info("  ✓ Final status: Plan #{$planId} = {$final->publication_status}");

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info(" Plan #{$planId} published successfully at {$final->published_at}");
        if ($currentPlan) {
            $this->info(" Previous plan #{$currentPlan->id} archived.");
        }
        $this->info(" Rollback command: php artisan stream-plans:rollback {$currentPlan?->id} --confirm");
        $this->info('═══════════════════════════════════════════════════════');

        return 0;
    }
}
