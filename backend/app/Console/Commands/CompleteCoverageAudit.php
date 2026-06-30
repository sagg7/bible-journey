<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Audits the gap between canonical coverage (chapters with own-book ReadingBlocks)
 * and complete-mode coverage (chapters marked complete_mode_required=1 in coverage paths).
 *
 * Run against Plan 9 to understand the 249-chapter gap before building Plan 9.1.
 */
class CompleteCoverageAudit extends Command
{
    protected $signature = 'stream-plans:complete-coverage-audit
                            {plan_id : Stream plan ID to audit}';

    protected $description = 'Audit chapters with own-book ReadingBlocks that are not required in Complete Mode';

    // Maps coverage path display_mode to report group
    private const GROUP_MAP = [
        'literary_window'            => 'literary_collection',
        'associated_reading'         => 'associated_poetry',
        'canonical_fallback_window'  => 'canonical_fallback',
        'prophetic_context'          => 'prophetic_context',
        'unresolved_prophetic_window'=> 'unresolved_prophetic_window',
        'epistolary_window'          => 'epistolary_window',
        'apocalyptic_literary_sequence' => 'apocalyptic_literary_sequence',
        'historical_bridge'          => 'historical_bridge',
    ];

    public function handle(): int
    {
        $planId = (int) $this->argument('plan_id');

        $plan = DB::table('stream_plans')->where('id', $planId)->first();
        if (! $plan) {
            $this->error("Plan #{$planId} not found.");
            return 1;
        }

        $this->info("Complete-mode coverage audit for Plan #{$planId}…");
        $this->newLine();

        // ── 1. Totals ───────────────────────────────────────────────────────────
        $totalPaths = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)->count();

        $requiredTotal = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('complete_mode_required', 1)->count();

        $coveredNotRequired = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('complete_mode_required', 0)
            ->where('display_mode', '!=', 'uncovered')->count();

        $uncovered = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('display_mode', 'uncovered')->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['total_paths',               $totalPaths],
                ['complete_mode_required',    $requiredTotal],
                ['covered_but_not_required',  $coveredNotRequired],
                ['uncovered',                 $uncovered],
                ['gap_to_close',              max(0, 1189 - $requiredTotal)],
            ]
        );

        if ($coveredNotRequired === 0) {
            $this->info('No gap — all covered chapters are already complete_mode_required.');
            return 0;
        }

        // ── 2. Detail per gap chapter ────────────────────────────────────────────
        $gapChapters = DB::table('chronological_coverage_paths as cp')
            ->join('biblical_books as bb', 'bb.id', '=', 'cp.bible_book_id')
            ->leftJoin('stream_plan_nodes as spn', 'spn.id', '=', 'cp.primary_stream_plan_node_id')
            ->leftJoin('chronological_reading_sets as crs', 'crs.id', '=', 'spn.crs_id')
            ->leftJoin('stream_plan_nodes as epn', 'epn.id', '=', 'cp.entry_point_node_id')
            ->where('cp.plan_id', $planId)
            ->where('cp.complete_mode_required', 0)
            ->where('cp.display_mode', '!=', 'uncovered')
            ->select(
                'bb.name_es as book',
                'bb.canonical_order',
                'cp.chapter',
                'cp.display_mode',
                'cp.narrative_flow_behavior',
                'cp.is_user_reachable',
                'cp.parent_era',
                'cp.entry_point_node_id',
                'crs.source_map as crs_source_map',
                'crs.stream_role',
                'crs.is_main_stream_node',
                DB::raw('(SELECT COUNT(*) FROM reading_blocks rb2
                           WHERE rb2.crs_id = crs.id
                           AND rb2.start_book_id = cp.bible_book_id
                           AND rb2.start_chapter <= cp.chapter
                           AND COALESCE(rb2.end_chapter, rb2.start_chapter) >= cp.chapter) as has_own_block')
            )
            ->orderBy('bb.canonical_order')
            ->orderBy('cp.chapter')
            ->get();

        // ── 3. Group by category ─────────────────────────────────────────────────
        $groups = [];
        foreach ($gapChapters as $row) {
            $group = self::GROUP_MAP[$row->display_mode] ?? 'other';
            $groups[$group][] = $row;
        }

        $summaryRows = [];
        $details = [];

        foreach ($groups as $groupName => $rows) {
            $summaryRows[] = [
                $groupName,
                count($rows),
                $rows[0]->stream_role ?? '—',
                $this->recommendedAction($groupName),
            ];

            foreach ($rows as $row) {
                $details[] = [
                    'group'               => $groupName,
                    'book'                => $row->book,
                    'chapter'             => $row->chapter,
                    'display_mode'        => $row->display_mode,
                    'narrative_behavior'  => $row->narrative_flow_behavior,
                    'is_user_reachable'   => (bool) $row->is_user_reachable,
                    'parent_era'          => $row->parent_era,
                    'entry_point_node_id' => $row->entry_point_node_id,
                    'crs_source_map'      => $row->crs_source_map,
                    'stream_role'         => $row->stream_role,
                    'is_main_stream_node' => (bool) $row->is_main_stream_node,
                    'has_own_block'       => (bool) $row->has_own_block,
                    'recommended_action'  => $this->recommendedAction($groupName),
                ];
            }
        }

        $this->line('Gap chapters by category:');
        $this->table(
            ['Category', 'Chapters', 'Stream Role', 'Recommended Action'],
            $summaryRows
        );

        // ── 4. Save reports ──────────────────────────────────────────────────────
        $report = [
            'plan_id'              => $planId,
            'audited_at'           => now()->toIso8601String(),
            'total_paths'          => $totalPaths,
            'complete_mode_required'       => $requiredTotal,
            'covered_but_not_required'     => $coveredNotRequired,
            'uncovered'            => $uncovered,
            'gap_to_close'         => max(0, 1189 - $requiredTotal),
            'groups'               => array_map(fn($rows) => count($rows), $groups),
            'details'              => $details,
        ];

        $report['fix_summary'] = $this->buildFixSummary($groups);

        Storage::makeDirectory('reports');
        $jsonPath = "reports/plan-{$planId}-complete-coverage-gap.json";
        $mdPath   = "reports/plan-{$planId}-complete-coverage-gap.md";

        Storage::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Storage::put($mdPath, $this->buildMarkdown($report, $groups));

        $this->newLine();
        $this->info("Reports saved:");
        $this->line("  " . storage_path("app/{$jsonPath}"));
        $this->line("  " . storage_path("app/{$mdPath}"));

        $this->newLine();
        $this->line('Fix command sequence:');
        $this->line('  1. php artisan migrate  (adds complete_mode_behavior to CRS)');
        $this->line('  2. Execute manifest_plan91.sql  (sets required_in_complete_mode=1 on gap blocks)');
        $this->line('  3. php artisan stream-plans:clone 9 --version=9.1 --purpose="complete-mode coverage correction"');
        $this->line('  4. php artisan coverage:build <new_plan_id>  (with fixed BuildCoveragePaths)');
        $this->line('  5. php artisan stream-plans:verify <new_plan_id>');

        return 0;
    }

    private function recommendedAction(string $group): string
    {
        return match ($group) {
            'literary_collection'            => 'Promote to Required Literary Window in Complete Mode',
            'associated_poetry'              => 'Promote to Required Associated Reading Window',
            'canonical_fallback'             => 'Promote to Required Canonical Fallback Window',
            'prophetic_context'              => 'Promote to Required Prophetic Window',
            'unresolved_prophetic_window'    => 'Promote to Required Unresolved Prophetic Window',
            'epistolary_window'              => 'Promote to Required Epistolary Window',
            'apocalyptic_literary_sequence'  => 'Promote to Required Apocalyptic Sequence',
            'historical_bridge'              => 'No reading blocks — mark context_only; no chapter coverage needed',
            default                          => 'Review and classify',
        };
    }

    private function buildFixSummary(array $groups): array
    {
        $summary = [];
        foreach ($groups as $group => $rows) {
            $blockIds = collect($rows)->pluck('crs_source_map')->unique()->filter()->values()->toArray();
            $summary[$group] = [
                'chapter_count' => count($rows),
                'action'        => $this->recommendedAction($group),
                'crs_maps'      => array_slice($blockIds, 0, 10),
            ];
        }
        return $summary;
    }

    private function buildMarkdown(array $report, array $groups): string
    {
        $lines = [
            "# Plan #{$report['plan_id']} — Complete-Mode Coverage Gap",
            "",
            "**Audited at:** {$report['audited_at']}",
            "",
            "## Summary",
            "",
            "| Metric | Value |",
            "|---|---|",
            "| Total coverage paths | {$report['total_paths']} |",
            "| Complete-mode required | {$report['complete_mode_required']} |",
            "| Covered but not required | {$report['covered_but_not_required']} |",
            "| Uncovered | {$report['uncovered']} |",
            "| **Gap to close** | **{$report['gap_to_close']}** |",
            "",
            "## Gap by Category",
            "",
            "| Category | Chapters | Action |",
            "|---|---|---|",
        ];

        foreach ($groups as $group => $rows) {
            $lines[] = "| {$group} | " . count($rows) . " | " . $this->recommendedAction($group) . " |";
        }

        $lines[] = "";
        $lines[] = "## Fix Sequence";
        $lines[] = "";
        $lines[] = "1. `php artisan migrate`";
        $lines[] = "2. Execute `manifest_plan91.sql` (sets `required_in_complete_mode=1` on all gap blocks)";
        $lines[] = "3. `php artisan stream-plans:clone 9 --version=9.1 --purpose=\"complete-mode coverage correction\"`";
        $lines[] = "4. `php artisan coverage:build <new_plan_id>`";
        $lines[] = "5. `php artisan stream-plans:verify <new_plan_id>`";

        return implode("\n", $lines) . "\n";
    }
}
