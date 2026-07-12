<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerifyStreamPlan extends Command
{
    protected $signature = 'stream-plans:verify
                            {plan_id : Stream plan ID to verify}
                            {--profile=cautious_default : Canon profile}';

    protected $description = 'Verify a stream plan passes all publication gates';

    public function handle(): int
    {
        $planId  = (int) $this->argument('plan_id');
        $profile = $this->option('profile');

        $plan = DB::table('stream_plans')->where('id', $planId)->first();
        if (! $plan) {
            $this->error("Plan #{$planId} not found.");
            return 1;
        }

        $this->info("Verifying Stream Plan #{$planId} (profile: {$profile})…");

        $report = [
            'plan_id'     => $planId,
            'profile'     => $profile,
            'verified_at' => now()->toIso8601String(),
            'gates'       => [],
            'overall'     => 'pass',
            'blocking_issues' => [],
        ];

        // ── Gate 1: Structural ────────────────────────────────────────────────
        $this->line('  [1/4] Structural coverage…');
        $structural = $this->runStructural($planId);
        $report['gates']['structural'] = $structural;
        if (! $structural['passed']) {
            $report['overall'] = 'fail';
            foreach ($structural['issues'] as $i) {
                $report['blocking_issues'][] = "structural: {$i}";
            }
        }

        // ── Gate 2: Text ──────────────────────────────────────────────────────
        $this->line('  [2/4] Text coverage…');
        $text = $this->runText($planId);
        $report['gates']['text'] = $text;
        if (! $text['passed']) {
            $report['overall'] = 'fail';
            foreach ($text['issues'] as $i) {
                $report['blocking_issues'][] = "text: {$i}";
            }
        }

        // ── Gate 3: Navigation ────────────────────────────────────────────────
        $this->line('  [3/4] Navigation…');
        $nav = $this->runNavigation($planId);
        $report['gates']['navigation'] = $nav;
        if (! $nav['passed']) {
            $report['overall'] = 'fail';
            foreach ($nav['issues'] as $i) {
                $report['blocking_issues'][] = "navigation: {$i}";
            }
        }

        // ── Gate 4: Reading modes ─────────────────────────────────────────────
        $this->line('  [4/4] Reading modes…');
        $modes = $this->runModes($planId, $nav, $text);
        $report['gates']['modes'] = $modes;
        if (! $modes['passed']) {
            $report['overall'] = 'fail';
            foreach ($modes['issues'] as $i) {
                $report['blocking_issues'][] = "modes: {$i}";
            }
        }

        // ── Save reports ──────────────────────────────────────────────────────
        Storage::makeDirectory('reports');
        $jsonPath = "reports/stream-plan-{$planId}-verification.json";
        $mdPath   = "reports/stream-plan-{$planId}-verification.md";

        Storage::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Storage::put($mdPath, $this->buildMarkdown($report, $plan));

        // ── Print summary ─────────────────────────────────────────────────────
        $this->newLine();
        $this->printSummary($report);

        $this->newLine();
        $this->line("Reports saved:");
        $this->line("  " . storage_path("app/{$jsonPath}"));
        $this->line("  " . storage_path("app/{$mdPath}"));

        return $report['overall'] === 'pass' ? 0 : 1;
    }

    // ── Gate implementations ──────────────────────────────────────────────────

    private function runStructural(int $planId): array
    {
        $expected = 1189;
        $issues   = [];

        $totalPaths = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)->count();

        $uncovered = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('display_mode', 'uncovered')
            ->count();

        $duplicates = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->select('bible_book_id', 'chapter', DB::raw('COUNT(*) as cnt'))
            ->groupBy('bible_book_id', 'chapter')
            ->havingRaw('cnt > 1')
            ->count();

        $ownBook = DB::select("
            SELECT COUNT(*) AS cnt
            FROM chronological_coverage_paths cp
            WHERE cp.plan_id = ?
            AND cp.display_mode != 'uncovered'
            AND EXISTS (
                SELECT 1 FROM reading_blocks rb
                JOIN stream_plan_nodes spn ON spn.crs_id = rb.crs_id AND spn.plan_id = ?
                WHERE rb.start_book_id = cp.bible_book_id
                  AND rb.start_chapter <= cp.chapter
                  AND COALESCE(rb.end_book_id, rb.start_book_id) = cp.bible_book_id
                  AND COALESCE(rb.end_chapter, rb.start_chapter) >= cp.chapter
            )
        ", [$planId, $planId]);
        $ownBookCount = $ownBook[0]->cnt ?? 0;

        if ($totalPaths !== $expected)        $issues[] = "expected {$expected} coverage rows, found {$totalPaths}";
        if ($uncovered !== 0)                 $issues[] = "{$uncovered} uncovered chapters";
        if ($duplicates !== 0)                $issues[] = "{$duplicates} duplicate primary coverage paths";
        if ($ownBookCount !== $expected)      $issues[] = "only {$ownBookCount}/{$expected} chapters have own-book reading block";

        return [
            'passed'                              => empty($issues),
            'expected_chapters'                   => $expected,
            'coverage_paths_found'                => $totalPaths,
            'chapters_with_own_book_reading_block'=> $ownBookCount,
            'duplicate_primary_coverage_paths'    => $duplicates,
            'uncovered_chapters'                  => $uncovered,
            'issues'                              => $issues,
        ];
    }

    private function runText(int $planId): array
    {
        $issues = [];

        $translation = DB::table('translations')
            ->where('can_display_full_text', 1)
            ->orderBy('sort_order')
            ->first();

        if (! $translation) {
            return [
                'passed' => false,
                'issues' => ['no active translation with full text found'],
                'active_translation'                    => null,
                'books_with_text'                       => 0,
                'chapters_with_text'                    => 0,
                'verses_with_text'                      => 0,
                'reading_blocks_with_resolvable_text'   => 0,
                'reading_blocks_without_text'           => 0,
            ];
        }

        $tid          = $translation->id;
        $booksWithText = DB::table('bible_chapters as bc')
            ->join('bible_verses as bv', 'bv.chapter_id', '=', 'bc.id')
            ->where('bv.translation_id', $tid)
            ->distinct('bc.biblical_book_id')
            ->count('bc.biblical_book_id');

        $chaptersWithText = DB::table('bible_chapters as bc')
            ->join('bible_verses as bv', 'bv.chapter_id', '=', 'bc.id')
            ->where('bv.translation_id', $tid)
            ->distinct('bc.id')
            ->count('bc.id');

        $versesWithText = DB::table('bible_verses')
            ->where('translation_id', $tid)->count();

        // Reading blocks in plan 9 that are required
        $totalRequired = DB::table('reading_blocks as rb')
            ->join('stream_plan_nodes as spn', function ($j) use ($planId) {
                $j->on('spn.crs_id', '=', 'rb.crs_id')->where('spn.plan_id', '=', $planId);
            })
            ->where('rb.required_in_complete_mode', 1)
            ->whereNotNull('rb.start_book_id')
            ->count();

        $blocksWithoutText = DB::select("
            SELECT COUNT(*) AS cnt
            FROM reading_blocks rb
            JOIN stream_plan_nodes spn ON spn.crs_id = rb.crs_id AND spn.plan_id = ?
            WHERE rb.required_in_complete_mode = 1
              AND rb.start_book_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM bible_chapters bc
                  JOIN bible_verses bv ON bv.chapter_id = bc.id
                  WHERE bc.biblical_book_id = rb.start_book_id
                    AND bc.chapter_number BETWEEN rb.start_chapter AND COALESCE(rb.end_chapter, rb.start_chapter)
                    AND bv.translation_id = ?
              )
        ", [$planId, $tid]);
        $noText = $blocksWithoutText[0]->cnt ?? 0;

        if ($booksWithText   !== 66)    $issues[] = "only {$booksWithText}/66 books have text";
        if ($chaptersWithText !== 1189) $issues[] = "only {$chaptersWithText}/1189 chapters have text";
        if ($noText > 0)                $issues[] = "{$noText} required reading blocks cannot resolve text";

        return [
            'passed'                              => empty($issues),
            'active_translation'                  => $translation->code,
            'books_with_text'                     => $booksWithText,
            'chapters_with_text'                  => $chaptersWithText,
            'verses_with_text'                    => $versesWithText,
            'reading_blocks_with_resolvable_text' => $totalRequired - $noText,
            'reading_blocks_without_text'         => $noText,
            'issues'                              => $issues,
        ];
    }

    private function runNavigation(int $planId): array
    {
        $issues = [];

        // Complete-mode required chapters (derived from reading_blocks.required_in_complete_mode
        // via BuildCoveragePaths — one coverage path per chapter)
        $requiredChaptersTotal = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('complete_mode_required', 1)
            ->count();

        $unreachableRequired = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('complete_mode_required', 1)
            ->where('is_user_reachable', 0)
            ->count();

        $requiredChaptersReachable = $requiredChaptersTotal - $unreachableRequired;

        // Required reading blocks (distinct records, for reporting)
        $requiredBlocksTotal = DB::table('reading_blocks as rb')
            ->join('stream_plan_nodes as spn', function ($j) use ($planId) {
                $j->on('spn.crs_id', '=', 'rb.crs_id')->where('spn.plan_id', '=', $planId);
            })
            ->where('rb.required_in_complete_mode', 1)
            ->whereNotNull('rb.start_book_id')
            ->count();

        // Narrative Flow deferred chapters (pending/optional but reachable)
        $narrativeDeferredTotal = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->whereIn('narrative_flow_behavior', ['pending', 'optional'])
            ->count();

        $narrativeDeferredReachable = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->whereIn('narrative_flow_behavior', ['pending', 'optional'])
            ->where('is_user_reachable', 1)
            ->count();

        $maxRank = DB::table('stream_plan_nodes')
            ->where('plan_id', $planId)
            ->where('is_main_stream_node', true)
            ->max('rank');

        $deadEnds = DB::select("
            SELECT COUNT(*) AS cnt
            FROM stream_plan_nodes spn
            WHERE spn.plan_id = ?
              AND spn.is_main_stream_node = 1
              AND spn.rank < ?
              AND NOT EXISTS (
                  SELECT 1 FROM stream_plan_edges spe
                  WHERE spe.plan_id = ? AND spe.from_node_id = spn.id
              )
        ", [$planId, $maxRank, $planId]);
        $deadEndCount = $deadEnds[0]->cnt ?? 0;

        // Orphaned windows: chapters covered by a node that are NOT user-reachable
        $orphanedWindows = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('is_user_reachable', 0)
            ->where('display_mode', '!=', 'uncovered')
            ->count();

        $orphanedFallbacks = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->where('is_user_reachable', 0)
            ->whereNotNull('primary_stream_plan_node_id')
            ->where('display_mode', 'canonical_fallback_window')
            ->count();

        // Cycles: impossible after Kahn's topo sort — verify edge count < node count
        $nodeCount = DB::table('stream_plan_nodes')->where('plan_id', $planId)->count();
        $edgeCount = DB::table('stream_plan_edges')->where('plan_id', $planId)->count();
        $cyclesDetected = ($edgeCount > 0 && $edgeCount >= $nodeCount) ? 1 : 0;

        // GATE: Complete Chronological Reading must require ALL 1,189 canonical chapters
        if ($requiredChaptersTotal !== 1189)
            $issues[] = "complete_mode only requires {$requiredChaptersTotal}/1189 chapters — all canonical chapters must be required";
        if ($unreachableRequired > 0)
            $issues[] = "{$unreachableRequired} complete_mode_required chapters are not user-reachable";
        if ($deadEndCount > 0)
            $issues[] = "{$deadEndCount} dead-end main-stream nodes";
        if ($orphanedWindows > 0)
            $issues[] = "{$orphanedWindows} secondary chapters are unreachable (no era entry point)";
        if ($cyclesDetected > 0)
            $issues[] = "possible cycles detected (edge count ≥ node count)";

        return [
            'passed'                              => empty($issues),
            'required_blocks_total'               => $requiredBlocksTotal,
            'complete_required_chapters_total'    => $requiredChaptersTotal,
            'complete_required_chapters_reachable'=> $requiredChaptersReachable,
            'complete_required_chapters_unreachable' => $unreachableRequired,
            'narrative_deferred_total'            => $narrativeDeferredTotal,
            'narrative_deferred_reachable'        => $narrativeDeferredReachable,
            'narrative_deferred_unrecoverable'    => $narrativeDeferredTotal - $narrativeDeferredReachable,
            'dead_end_nodes'                      => $deadEndCount,
            'orphaned_windows'                    => $orphanedWindows,
            'orphaned_fallbacks'                  => $orphanedFallbacks,
            'cycles_detected'                     => $cyclesDetected,
            'issues'                              => $issues,
            // Legacy aliases for backward compatibility
            'required_chapters_total'             => $requiredChaptersTotal,
            'required_blocks_reachable'           => $requiredChaptersReachable,
        ];
    }

    private function runModes(int $planId, array $nav, array $text): array
    {
        $issues = [];

        // Complete Chronological Reading: all 1,189 chapters required AND all reachable
        $ccrPass = $nav['complete_required_chapters_total'] === 1189
                   && $nav['complete_required_chapters_unreachable'] === 0
                   && $nav['dead_end_nodes'] === 0;

        // Narrative Flow: there are pending/optional paths
        $nfPaths = DB::table('chronological_coverage_paths')
            ->where('plan_id', $planId)
            ->whereIn('narrative_flow_behavior', ['pending', 'optional'])
            ->count();
        $nfPass = $nfPaths > 0 && $text['reading_blocks_without_text'] === 0;

        // Canonical Reading: 66 books accessible + all chapters have text
        $canonPass = $text['books_with_text'] === 66 && $text['chapters_with_text'] === 1189;

        if (! $ccrPass) {
            if ($nav['complete_required_chapters_total'] !== 1189) {
                $issues[] = 'Complete Chronological Reading: only ' . $nav['complete_required_chapters_total'] . '/1189 chapters are required — run manifest_plan91.sql then rebuild coverage';
            } elseif ($nav['complete_required_chapters_unreachable'] > 0) {
                $issues[] = 'Complete Chronological Reading: ' . $nav['complete_required_chapters_unreachable'] . ' required chapters are unreachable';
            } else {
                $issues[] = 'Complete Chronological Reading: dead-end nodes prevent full traversal';
            }
        }
        if (! $nfPass) {
            if ($nfPaths === 0) {
                $issues[] = 'Narrative Flow: no pending/optional paths found';
            } else {
                $issues[] = "Narrative Flow: {$nfPaths} deferred paths exist but text gate failed ({$text['reading_blocks_without_text']} required blocks have no text)";
            }
        }
        if (! $canonPass) $issues[] = 'Canonical Reading: not all 66 books have full text';

        return [
            'passed'                       => empty($issues),
            'complete_chronological_reading'=> $ccrPass ? 'pass' : 'fail',
            'narrative_flow'               => $nfPass ? 'pass' : 'fail',
            'canonical_reading'            => $canonPass ? 'pass' : 'fail',
            'narrative_flow_pending_paths' => $nfPaths,
            'issues'                       => $issues,
        ];
    }

    // ── Report formatters ─────────────────────────────────────────────────────

    private function printSummary(array $report): void
    {
        $icon = fn($b) => $b ? '<fg=green>✓</>' : '<fg=red>✗</>';

        $s = $report['gates']['structural'];
        $t = $report['gates']['text'];
        $n = $report['gates']['navigation'];
        $m = $report['gates']['modes'];

        $this->line("┌─ Plan #{$report['plan_id']} Verification ─────────────────────────────");
        $this->line("│ Structural coverage:");
        $this->line("│   {$icon($s['passed'])} expected_chapters             = {$s['expected_chapters']}");
        $this->line("│   {$icon($s['passed'])} own_book_reading_block        = {$s['chapters_with_own_book_reading_block']}");
        $this->line("│   {$icon($s['duplicate_primary_coverage_paths']===0)} duplicate_primary_paths       = {$s['duplicate_primary_coverage_paths']}");
        $this->line("│   {$icon($s['uncovered_chapters']===0)} uncovered_chapters            = {$s['uncovered_chapters']}");
        $this->line("│ Text coverage:");
        $this->line("│   {$icon($t['passed'])} active_translation            = " . ($t['active_translation'] ?? 'none'));
        $this->line("│   {$icon($t['books_with_text']===66)} books_with_text               = {$t['books_with_text']}/66");
        $this->line("│   {$icon($t['chapters_with_text']===1189)} chapters_with_text            = {$t['chapters_with_text']}/1189");
        $this->line("│   {$icon($t['passed'])} verses_with_text              = {$t['verses_with_text']}");
        $this->line("│   {$icon($t['reading_blocks_without_text']===0)} reading_blocks_without_text   = {$t['reading_blocks_without_text']}");
        $this->line("│ Complete Chronological Reading:");
        $this->line("│   {$icon($n['complete_required_chapters_total']===1189)} complete_required_total       = {$n['complete_required_chapters_total']}/1189");
        $this->line("│   {$icon($n['complete_required_chapters_unreachable']===0)} complete_required_unreachable = {$n['complete_required_chapters_unreachable']}");
        $this->line("│   {$icon($n['complete_required_chapters_reachable']===$n['complete_required_chapters_total'])} complete_required_reachable   = {$n['complete_required_chapters_reachable']}/{$n['complete_required_chapters_total']}");
        $this->line("│   {$icon($n['dead_end_nodes']===0)} dead_end_nodes                = {$n['dead_end_nodes']}");
        $this->line("│   {$icon($n['orphaned_windows']===0)} orphaned_windows              = {$n['orphaned_windows']}");
        $this->line("│   {$icon($n['cycles_detected']===0)} cycles_detected               = {$n['cycles_detected']}");
        $this->line("│ Narrative Flow:");
        $ndUnrec = $n['narrative_deferred_unrecoverable'];
        $this->line("│   — deferred_chapters                = {$n['narrative_deferred_total']}");
        $this->line("│   {$icon($ndUnrec===0)} deferred_recoverable          = {$n['narrative_deferred_reachable']}");
        $this->line("│   {$icon($ndUnrec===0)} deferred_unrecoverable        = {$ndUnrec}");
        $this->line("│ Modes:");
        $this->line("│   {$icon($m['complete_chronological_reading']==='pass')} complete_chronological_reading = {$m['complete_chronological_reading']}");
        $this->line("│   {$icon($m['narrative_flow']==='pass')} narrative_flow                = {$m['narrative_flow']}");
        $this->line("│   {$icon($m['canonical_reading']==='pass')} canonical_reading             = {$m['canonical_reading']}");

        $overall = $report['overall'] === 'pass'
            ? '<fg=green;options=bold>PASS</>'
            : '<fg=red;options=bold>FAIL</>';
        $this->line("└─ Overall: {$overall}");

        if (! empty($report['blocking_issues'])) {
            $this->newLine();
            $this->error('Blocking issues:');
            foreach ($report['blocking_issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        }
    }

    private function buildMarkdown(array $report, object $plan): string
    {
        $s = $report['gates']['structural'];
        $t = $report['gates']['text'];
        $n = $report['gates']['navigation'];
        $m = $report['gates']['modes'];
        $ok = fn($b) => $b ? '✅' : '❌';

        $lines = [
            "# Stream Plan #{$report['plan_id']} Verification Report",
            "",
            "**Verified at:** {$report['verified_at']}  ",
            "**Profile:** {$report['profile']}  ",
            "**Status:** {$plan->publication_status}  ",
            "**Overall:** " . strtoupper($report['overall']),
            "",
            "## Structural Coverage",
            "",
            "| Check | Value | Gate |",
            "|---|---|---|",
            "| expected_chapters | {$s['expected_chapters']} | {$ok(true)} |",
            "| chapters_with_own_book_reading_block | {$s['chapters_with_own_book_reading_block']} | {$ok($s['chapters_with_own_book_reading_block']===1189)} |",
            "| duplicate_primary_coverage_paths | {$s['duplicate_primary_coverage_paths']} | {$ok($s['duplicate_primary_coverage_paths']===0)} |",
            "| uncovered_chapters | {$s['uncovered_chapters']} | {$ok($s['uncovered_chapters']===0)} |",
            "",
            "## Text Coverage",
            "",
            "| Check | Value | Gate |",
            "|---|---|---|",
            "| active_translation | " . ($t['active_translation'] ?? 'none') . " | {$ok($t['active_translation']!==null)} |",
            "| books_with_text | {$t['books_with_text']}/66 | {$ok($t['books_with_text']===66)} |",
            "| chapters_with_text | {$t['chapters_with_text']}/1189 | {$ok($t['chapters_with_text']===1189)} |",
            "| verses_with_text | {$t['verses_with_text']} | {$ok($t['verses_with_text']>0)} |",
            "| reading_blocks_without_text | {$t['reading_blocks_without_text']} | {$ok($t['reading_blocks_without_text']===0)} |",
            "",
            "## Complete Chronological Reading",
            "",
            "| Check | Value | Gate |",
            "|---|---|---|",
            "| complete_required_chapters_total | {$n['complete_required_chapters_total']} | {$ok($n['complete_required_chapters_total']===1189)} |",
            "| complete_required_chapters_reachable | {$n['complete_required_chapters_reachable']} | {$ok($n['complete_required_chapters_reachable']===$n['complete_required_chapters_total'])} |",
            "| complete_required_chapters_unreachable | {$n['complete_required_chapters_unreachable']} | {$ok($n['complete_required_chapters_unreachable']===0)} |",
            "| dead_end_nodes | {$n['dead_end_nodes']} | {$ok($n['dead_end_nodes']===0)} |",
            "| orphaned_windows | {$n['orphaned_windows']} | {$ok($n['orphaned_windows']===0)} |",
            "| orphaned_fallbacks | {$n['orphaned_fallbacks']} | {$ok($n['orphaned_fallbacks']===0)} |",
            "| cycles_detected | {$n['cycles_detected']} | {$ok($n['cycles_detected']===0)} |",
            "",
            "## Narrative Flow",
            "",
            "| Check | Value | Gate |",
            "|---|---|---|",
            "| narrative_deferred_total | {$n['narrative_deferred_total']} | — |",
            "| narrative_deferred_recoverable | {$n['narrative_deferred_reachable']} | {$ok($n['narrative_deferred_reachable']===$n['narrative_deferred_total'])} |",
            "| narrative_deferred_unrecoverable | {$n['narrative_deferred_unrecoverable']} | {$ok($n['narrative_deferred_unrecoverable']===0)} |",
            "",
            "## Reading Modes",
            "",
            "| Mode | Result |",
            "|---|---|",
            "| Complete Chronological Reading | {$ok($m['complete_chronological_reading']==='pass')} {$m['complete_chronological_reading']} |",
            "| Narrative Flow | {$ok($m['narrative_flow']==='pass')} {$m['narrative_flow']} |",
            "| Canonical Reading | {$ok($m['canonical_reading']==='pass')} {$m['canonical_reading']} |",
        ];

        if (! empty($report['blocking_issues'])) {
            $lines[] = "";
            $lines[] = "## Blocking Issues";
            $lines[] = "";
            foreach ($report['blocking_issues'] as $issue) {
                $lines[] = "- {$issue}";
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
