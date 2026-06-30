<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Harmonization\HarmonizationResolver;

class CompileStreamPlan extends Command
{
    protected $signature   = 'harmonize:compile
                                {profile=cautious_default : Harmonization profile ID}
                                {--locale=es : Locale for this plan}
                                {--dry-run : Run without writing to DB}';

    protected $description = 'Compile CRS data into a published StreamPlan';

    public function handle(): int
    {
        $profile = $this->argument('profile');
        $locale  = $this->option('locale');
        $dryRun  = $this->option('dry-run');

        $this->info("Compiling StreamPlan — profile: $profile | locale: $locale");
        if ($dryRun) $this->warn('DRY RUN — no data will be written.');

        $resolver = new HarmonizationResolver($profile, $locale);

        try {
            $result = $resolver->compile($dryRun);
        } catch (\Throwable $e) {
            $this->error('Compilation failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('─── Dry-run report ───────────────────────');
            $this->line(" Nodes        : {$result['node_count']}");
            $this->line(" Edges        : {$result['edge_count']}");
            $this->line(" Warnings     : " . count($result['warnings']));
            $this->line(" Errors       : " . count($result['errors']));

            if (! empty($result['warnings'])) {
                $this->newLine();
                $this->warn(' Warnings:');
                foreach (array_slice($result['warnings'], 0, 8) as $w) {
                    $this->line("  · $w");
                }
            }

            if (! empty($result['sample_nodes'])) {
                $this->newLine();
                $this->line(' Sample nodes (first 10 by rank):');
                foreach ($result['sample_nodes'] as $n) {
                    $this->line("  [{$n['rank']}] {$n['source_map']}");
                }
            }
            $this->info('──────────────────────────────────────────');
            return self::SUCCESS;
        }

        // Real compile
        $warnings = $resolver->getWarnings();

        $this->newLine();
        $this->info('─── Compilation report ───────────────────');
        $this->line(" Plan ID      : {$result->id}");
        $this->line(" Profile      : {$result->profile_id}");
        $this->line(" Status       : {$result->publication_status}");
        $this->line(" Nodes        : {$result->node_count}");
        $this->line(" Edges        : {$result->edge_count}");
        $this->line(" Published at : {$result->published_at}");
        $this->line(" Hash         : " . substr($result->validation_hash ?? '', 0, 16) . '…');

        if (! empty($warnings)) {
            $this->newLine();
            $this->warn(' Warnings (' . count($warnings) . '):');
            foreach (array_slice($warnings, 0, 5) as $w) {
                $this->line("  · $w");
            }
        }
        $this->info('──────────────────────────────────────────');

        return self::SUCCESS;
    }
}
