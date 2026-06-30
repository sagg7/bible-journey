<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RollbackStreamPlan extends Command
{
    protected $signature = 'stream-plans:rollback
                            {plan_id : The previously-published plan to restore}
                            {--confirm : Required to prevent accidental rollback}';

    protected $description = 'Roll back to a previously published stream plan';

    public function handle(): int
    {
        $targetId = (int) $this->argument('plan_id');

        if (! $this->option('confirm')) {
            $this->error('Requires --confirm flag.');
            $this->line('Usage: php artisan stream-plans:rollback ' . $targetId . ' --confirm');
            return 1;
        }

        $targetPlan = DB::table('stream_plans')->where('id', $targetId)->first();
        if (! $targetPlan) {
            $this->error("Plan #{$targetId} not found.");
            return 1;
        }

        if ($targetPlan->publication_status === 'published') {
            $this->error("Plan #{$targetId} is already published. No rollback needed.");
            return 1;
        }

        // Find currently published plan
        $currentPlan = DB::table('stream_plans')
            ->where('publication_status', 'published')
            ->latest('published_at')
            ->first();

        $this->warn("Rolling back: Plan #{$currentPlan?->id} → Plan #{$targetId}");
        $this->newLine();

        DB::transaction(function () use ($targetId, $currentPlan) {
            if ($currentPlan) {
                DB::table('stream_plans')
                    ->where('id', $currentPlan->id)
                    ->update(['publication_status' => 'draft', 'updated_at' => now()]);
            }

            DB::table('stream_plans')
                ->where('id', $targetId)
                ->update(['publication_status' => 'published', 'updated_at' => now()]);
        });

        Cache::forget('stream_plan_active');
        Cache::forget("stream_plan_{$targetId}");
        if ($currentPlan) {
            Cache::forget("stream_plan_{$currentPlan->id}");
        }

        $this->info("✓ Plan #{$targetId} restored to published.");
        if ($currentPlan) {
            $this->info("✓ Plan #{$currentPlan->id} set to draft.");
        }
        $this->info('✓ Cache cleared.');

        return 0;
    }
}
