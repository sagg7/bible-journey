<?php

namespace App\Console\Commands;

use App\Models\BiblicalBook;
use App\Models\ChronologicalCoveragePath;
use App\Models\StreamPlan;
use App\Models\StreamPlanNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds the chronological_coverage_paths table for a given stream plan.
 *
 * For every chapter in every biblical book, records:
 * - Which stream plan node covers it as primary
 * - Whether it is reachable by the user
 * - How it is accessed (main stream, literary window, etc.)
 *
 * Run after each harmonize:compile to keep coverage data current.
 */
class BuildCoveragePaths extends Command
{
    protected $signature = 'coverage:build {plan_id? : Stream plan ID (defaults to latest published)}';
    protected $description = 'Build ChronologicalCoveragePaths for every book/chapter against a stream plan';

    public function handle(): int
    {
        $planId = $this->argument('plan_id');

        if ($planId) {
            $plan = StreamPlan::find($planId);
        } else {
            $plan = StreamPlan::latestPublished('cautious_default', 'es');
        }

        if (! $plan) {
            $this->error('No plan found. Run php artisan harmonize:compile first.');
            return 1;
        }

        $this->info("Building coverage paths for Plan #{$plan->id}…");

        // Clear existing paths for this plan
        ChronologicalCoveragePath::where('plan_id', $plan->id)->delete();

        $books     = BiblicalBook::orderBy('canonical_order')->get();
        $inserted  = 0;
        $missing   = 0;

        // Pre-load all nodes for this plan with their CRS relationships
        $nodesByCrs = StreamPlanNode::where('plan_id', $plan->id)
            ->with(['crs' => fn($q) => $q->select(
                'id','stream_role','user_facing_era','user_facing_era_sort',
                'is_main_stream_node','placement_confidence','display_mode'
            )])
            ->get()
            ->keyBy('crs_id');

        // Pre-load all reading blocks with book info
        $blocks = DB::table('reading_blocks as rb')
            ->join('chronological_reading_sets as crs', 'crs.id', '=', 'rb.crs_id')
            ->join('biblical_books as bb_start', 'bb_start.id', '=', 'rb.start_book_id')
            ->leftJoin('biblical_books as bb_end', 'bb_end.id', '=', 'rb.end_book_id')
            ->select(
                'rb.crs_id',
                'rb.start_book_id',
                'rb.start_chapter',
                'rb.end_book_id',
                'rb.end_chapter',
                'rb.required_in_complete_mode',
                'bb_start.canonical_order as start_book_order',
                'bb_end.canonical_order as end_book_order',
                'crs.is_main_stream_node',
                'crs.stream_role',
                'crs.user_facing_era',
                'crs.user_facing_era_sort',
                'crs.placement_confidence',
                'crs.display_mode as crs_display_mode'
            )
            ->whereNotNull('rb.start_book_id')
            ->get();

        $rows = [];

        foreach ($books as $book) {
            for ($chapter = 1; $chapter <= $book->chapter_count; $chapter++) {

                // Find all blocks that cover this book/chapter
                $matching = $blocks->filter(function ($b) use ($book, $chapter) {
                    $startOrder = $b->start_book_order;
                    $endOrder   = $b->end_book_order ?? $startOrder;
                    $bookOrder  = $book->canonical_order;

                    // Block spans across this book entirely
                    if ($startOrder < $bookOrder && $endOrder > $bookOrder) {
                        return true;
                    }
                    // Block starts in this book
                    if ($b->start_book_id == $book->id && $b->start_chapter <= $chapter) {
                        // Ends in same book
                        if (($b->end_book_id == $book->id || $b->end_book_id === null) && $b->end_chapter >= $chapter) {
                            return true;
                        }
                        // Ends in a later book (so all chapters from start to end of this book are covered)
                        if ($b->end_book_id != $book->id && $endOrder > $bookOrder) {
                            return true;
                        }
                    }
                    // Block started in earlier book, ends in this book
                    if ($b->end_book_id == $book->id && $startOrder < $bookOrder && $b->end_chapter >= $chapter) {
                        return true;
                    }
                    return false;
                });

                if ($matching->isEmpty()) {
                    // Chapter has no coverage at all
                    $missing++;
                    $rows[] = [
                        'plan_id'                    => $plan->id,
                        'bible_book_id'              => $book->id,
                        'chapter'                    => $chapter,
                        'primary_stream_plan_node_id'=> null,
                        'parent_era'                 => null,
                        'parent_era_sort'            => null,
                        'entry_point_node_id'        => null,
                        'display_mode'               => 'uncovered',
                        'complete_mode_required'     => false,
                        'narrative_flow_behavior'    => 'excluded',
                        'is_user_reachable'          => false,
                        'rationale'                  => 'No reading block covers this chapter in the current plan.',
                        'placement_confidence'       => null,
                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ];
                    continue;
                }

                // Sort: main stream first, then by user_facing_era_sort
                $sorted = $matching->sortByDesc('is_main_stream_node')
                                   ->sortBy('user_facing_era_sort');
                $best   = $sorted->first();

                $node   = $nodesByCrs->get($best->crs_id);

                // Determine display_mode for this path
                $pathMode = $this->resolvePathMode($best);

                // Determine narrative_flow_behavior
                // stream_role is the semantic discriminator; is_main_stream_node is secondary.
                // Nodes like canonical_fallback/apocalyptic can be main-stream graph members
                // but still need Narrative Flow to defer them (they're windows, not anchors).
                $nfBehavior = match (true) {
                    $best->stream_role === 'main_historical_event'  => 'included',
                    in_array($best->stream_role, [
                        'associated_poetry', 'literary_collection',
                        'prophetic_context', 'unresolved_prophetic_window',
                        'apocalyptic_literary_sequence',
                        'epistolary_context',
                        'editorial_context', 'composition_context', 'genealogy_context',
                    ])                                              => 'pending',
                    in_array($best->stream_role, ['canonical_fallback', 'historical_bridge']) => 'optional',
                    $best->is_main_stream_node == 1                 => 'included',
                    default                                         => 'pending',
                };

                $isReachable = ($node !== null);
                $entryPointId = $node?->id;

                // For hidden nodes, find the first main-stream node in their parent era as entry point
                if ($node && ! $node->is_main_stream_node && $best->user_facing_era) {
                    $eraFirstNode = StreamPlanNode::where('plan_id', $plan->id)
                        ->where('is_main_stream_node', true)
                        ->where('user_facing_era', $best->user_facing_era)
                        ->orderBy('rank')
                        ->value('id');
                    if ($eraFirstNode) {
                        $entryPointId = $eraFirstNode;
                        $isReachable  = true;
                    }
                }

                $rows[] = [
                    'plan_id'                    => $plan->id,
                    'bible_book_id'              => $book->id,
                    'chapter'                    => $chapter,
                    'primary_stream_plan_node_id'=> $node?->id,
                    'parent_era'                 => $best->user_facing_era,
                    'parent_era_sort'            => $best->user_facing_era_sort,
                    'entry_point_node_id'        => $entryPointId,
                    'display_mode'               => $pathMode,
                    // Requerido si CUALQUIER bloque del plan que cubre este
                    // capítulo lo exige — el primario puede ser una ventana
                    // opcional mientras el fallback canónico sigue requerido.
                    'complete_mode_required'     => $matching->contains(
                        fn ($b) => (bool) $b->required_in_complete_mode
                    ),
                    'narrative_flow_behavior'    => $nfBehavior,
                    'is_user_reachable'          => $isReachable,
                    'rationale'                  => $best->stream_role . ($best->user_facing_era ? ' — ' . $best->user_facing_era : ''),
                    'placement_confidence'       => $best->placement_confidence,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ];
                $inserted++;
            }
        }

        // Batch insert
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('chronological_coverage_paths')->insert($chunk);
        }

        $total    = $inserted + $missing;
        $reachPct = $total > 0 ? round($inserted / $total * 100, 1) : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total chapters',      $total],
                ['Covered (reachable)', $inserted],
                ['Uncovered',           $missing],
                ['Reachability',        "{$reachPct}%"],
            ]
        );

        // Per-display_mode breakdown
        $modes = DB::table('chronological_coverage_paths')
            ->where('plan_id', $plan->id)
            ->select('display_mode', DB::raw('COUNT(*) as cnt'))
            ->groupBy('display_mode')
            ->orderByDesc('cnt')
            ->get();

        $this->info("\nCoverage by display mode:");
        foreach ($modes as $m) {
            $this->line("  {$m->display_mode}: {$m->cnt}");
        }

        if ($missing > 0) {
            $this->warn("\n{$missing} chapters have no coverage path — add CRS to cover them.");
            // Show first 20 uncovered
            $uncovered = DB::table('chronological_coverage_paths as cp')
                ->join('biblical_books as bb', 'bb.id', '=', 'cp.bible_book_id')
                ->where('cp.plan_id', $plan->id)
                ->where('cp.display_mode', 'uncovered')
                ->select('bb.name_es', 'cp.chapter')
                ->orderBy('bb.canonical_order')
                ->orderBy('cp.chapter')
                ->limit(20)
                ->get();
            foreach ($uncovered as $u) {
                $this->line("  {$u->name_es} {$u->chapter}");
            }
            if ($missing > 20) {
                $this->line("  … and " . ($missing - 20) . " more.");
            }
        } else {
            $this->info('All 1,189 chapters have a coverage path.');
        }

        return 0;
    }

    private function resolvePathMode(object $block): string
    {
        if ($block->crs_display_mode && $block->crs_display_mode !== 'full') {
            return $block->crs_display_mode;
        }
        return match ($block->stream_role) {
            'main_historical_event'          => 'main_historical_event',
            'prophetic_context'              => 'prophetic_context',
            'associated_poetry'              => 'associated_reading',
            'literary_collection'            => 'literary_window',
            'canonical_fallback'             => 'canonical_fallback_window',
            'historical_bridge'              => 'historical_bridge',
            'genealogy_context'              => 'literary_window',
            'editorial_context'              => 'literary_window',
            'composition_context'            => 'literary_window',
            'apocalyptic_literary_sequence'  => 'apocalyptic_literary_sequence',
            default                          => 'literary_window',
        };
    }
}
