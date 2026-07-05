<?php

namespace App\Services\Harmonization;

use App\Models\ChronologicalReadingSet;
use App\Models\ParallelLink;
use App\Models\StreamPlan;
use App\Models\StreamPlanNode;
use App\Models\StreamPlanEdge;
use App\Models\LedgerSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HarmonizationResolver
{
    private string $profileId;
    private string $locale;
    private array  $warnings = [];
    private array  $errors   = [];

    // Edge score weights (sum to 1.0)
    private const W_EVIDENCE  = 0.35;
    private const W_RELATION  = 0.25;
    private const W_PLACEMENT = 0.20;
    private const W_REVIEW    = 0.15;
    private const W_PROFILE   = 0.05;

    // Chronological order of era_slugs across the full Bible timeline.
    // Used to break ties when sort_key alone can't distinguish cross-era order.
    private const ERA_CANONICAL_ORDER = [
        // OT — pre-monarchy
        'primeval-history'                               =>   5,
        'patriarchs'                                     =>  10,
        'exodus-sinai'                                   =>  20,
        'wilderness'                                     =>  30,
        'plains-of-moab'                                 =>  40,
        'conquest-settlement'                            =>  50,
        'judges'                                         =>  60,
        // OT — monarchy
        'rise-of-the-monarchy'                           =>  70,
        'united-monarchy'                                =>  80,
        'genealogical-retrospective'                     =>  85,
        'postexilic-retrospective'                       =>  86,
        'psalter-editorial-frame'                        =>  87,
        'davidic-window'                                 =>  88,
        'davidic-poetry-collection'                      =>  89,
        'davidic-temple-poetry-collections'              =>  90,
        'solomonic-window'                               =>  91,
        'temple-communal-poetry-collections'             =>  92,
        'mosaic-tradition'                               =>  93,
        'psalter-collection'                             =>  94,
        'davidic-pilgrimage-tradition'                   =>  95,
        'pilgrimage-collection'                          =>  96,
        'davidic-covenant-temple-tradition'              =>  97,
        'pilgrimage-and-exile-memory'                    =>  98,
        'psalter-conclusion'                             =>  99,
        // OT — divided kingdom
        'divided-monarchy'                               => 100,
        'divided-kingdom'                                => 101,
        'wisdom-literature-chronology-unresolved'        => 102,
        'solomonic-wisdom-tradition'                     => 103,
        'wisdom-collection'                              => 104,
        'northern-prosperity-and-early-assyrian-horizon' => 110,
        'northern-kingdoms-final-decades'                => 111,
        'judah-before-the-syro-ephraimite-crisis'        => 112,
        'syro-ephraimite-crisis'                         => 113,
        'assyrian-horizon-in-judah'                      => 114,
        'hezekiah-and-the-assyrian-crisis'               => 115,
        'judah-under-assyria'                            => 116,
        'hezekiahs-reign-collection-note'                => 117,
        'late-assyrian-collapse'                         => 120,
        'josiahs-reign'                                  => 121,
        'judahs-final-reforms'                           => 122,
        // OT — Babylonian period
        'jehoiakims-accession-and-early-reign'           => 130,
        'jehoiakims-reign'                               => 131,
        'jehoiakims-fourth-year'                         => 132,
        'jehoiakim-and-the-approach-of-babylon'          => 133,
        'babylonian-ascent'                              => 134,
        'zedekiahs-reign'                                => 135,
        'after-the-first-exile-zedekiah'                 => 136,
        'zedekiahs-fourth-year'                          => 137,
        'zedekiahs-reign-exiles-in-babylon'              => 138,
        'zedekiahs-tenth-year-siege'                     => 139,
        'zedekiahs-siege'                                => 140,
        'fall-of-jerusalem'                              => 141,
        'aftermath-of-jerusalems-fall'                   => 142,
        'babylonian-exile'                               => 143,
        // OT — post-exilic
        'late-judah'                                     => 150,
        'persian-transition'                             => 151,
        'return-and-reconstruction'                      => 152,
        'postexilic-prophetic-collection'                => 153,
        'persian-period'                                 => 154,
        'post-exilic-restoration-horizon'                => 155,
        'chronological-placement-unresolved'             => 160,
        // Intertestamental bridge
        'intertestamental-period'                        => 190,
        // NT
        'gospels-ministry-of-jesus'                      => 200,
        'gospel-harmony'                                 => 201,
        'acts-of-the-apostles'                           => 209,
        'acts-jerusalem'                                 => 210,
        'acts-samaria-and-judea'                         => 211,
        'acts-damascus-and-judea'                        => 212,
        'acts-coastal-judea-and-caesarea'                => 213,
        'acts-antioch'                                   => 214,
        'acts-jerusalem-and-antioch'                     => 215,
        'first-mission'                                  => 216,
        'pauline-letter'                                 => 217,
        'pauline-missions'                               => 217.5,
        'second-mission'                                 => 218,
        'third-mission'                                  => 219,
        'acts-caesarea'                                  => 220,
        'acts-mediterranean'                             => 221,
        'acts-rome'                                      => 222,
        'pauline-letters'                                => 223,
        'correspondence-beyond-acts'                     => 224,
        'general-letters-and-apocalypse'                 => 225,
        'apocalyptic-witness'                            => 230,
    ];

    // Confidence → numeric score
    private const CONFIDENCE_SCORE = [
        'alta'              => 1.0,
        'probable'          => 0.75,
        'debatida'          => 0.50,
        'tradicion_popular' => 0.30,
        'especulativa'      => 0.10,
    ];

    // Review status → numeric score
    private const REVIEW_SCORE = [
        'approved'      => 1.0,
        'needs_review'  => 0.60,
        'draft'         => 0.30,
        'blocked'       => 0.0,
    ];

    public function __construct(string $profileId = 'cautious_default', string $locale = 'es')
    {
        $this->profileId = $profileId;
        $this->locale    = $locale;
    }

    // ─── Public API ───────────────────────────────

    public function compile(bool $dryRun = false): StreamPlan|array
    {
        $this->warnings = [];
        $this->errors   = [];

        $nodes = $this->buildNodes();
        $edges = $this->buildEdges($nodes);

        // Hard constraints
        $this->applyHardConstraints($nodes, $edges);

        // Cycle detection — loop until none remain. Breaking one cycle in a densely
        // overlapping cluster (e.g. Samuel/Chronicles/Psalms parallel accounts) can leave
        // another overlapping cycle intact, since its edges may not have been touched.
        for ($pass = 0; $pass < 25; $pass++) {
            $cycles = $this->detectCycles($nodes, $edges);
            if (empty($cycles)) break;
            foreach ($cycles as $cycle) {
                $this->warnings[] = "Cycle detected: " . implode(' → ', $cycle);
            }
            $edges = $this->breakCycles($nodes, $edges, $cycles);
        }

        // Topological order
        $ordered = $this->topologicalOrder($nodes, $edges);

        // Assign ranks
        foreach ($ordered as $rank => $nodeId) {
            $nodes[$nodeId]['rank'] = $rank + 1;
        }

        if ($dryRun) {
            return [
                'node_count'  => count($nodes),
                'edge_count'  => count($edges),
                'warnings'    => $this->warnings,
                'errors'      => $this->errors,
                'sample_nodes'=> array_slice(
                    array_map(fn($n) => ['source_map' => $n['source_map'], 'rank' => $n['rank']], $nodes),
                    0, 10
                ),
            ];
        }

        return $this->persist($nodes, $edges);
    }

    // ─── Build nodes ──────────────────────────────

    private function buildNodes(): array
    {
        $nodes   = [];
        $crsRows = ChronologicalReadingSet::where('review_status', '!=', 'blocked')
            ->orderBy('sort_key')
            ->orderBy('source_map')
            ->get();

        foreach ($crsRows as $crs) {
            $displayMode = $this->resolveDisplayMode($crs);
            $requiredState = $this->resolveRequiredState($crs);

            $nodes[$crs->id] = [
                'id'                   => $crs->id,
                'crs_id'               => $crs->id,
                'source_map'           => $crs->source_map,
                'sort_key'             => $crs->sort_key,
                'era_slug'             => $crs->era_slug,
                'stream_role'          => $crs->stream_role,
                'user_facing_era'      => $crs->user_facing_era,
                'user_facing_era_sort' => $crs->user_facing_era_sort,
                'is_main_stream_node'  => (bool) $crs->is_main_stream_node,
                'display_mode'         => $displayMode,
                'required_state'       => $requiredState,
                'rank'                 => 0,
                'confidence'           => $crs->placement_confidence,
                'review_status'        => $crs->review_status,
            ];
        }

        return $nodes;
    }

    // ─── Build edges ──────────────────────────────

    private function buildEdges(array $nodes): array
    {
        $edges = [];

        // 1. Sequential edges within each era (sort_key order)
        $byEra = [];
        foreach ($nodes as $node) {
            $byEra[$node['era_slug']][] = $node;
        }

        foreach ($byEra as $eraSlug => $eraNodes) {
            usort($eraNodes, fn($a, $b) => $a['sort_key'] <=> $b['sort_key']);
            for ($i = 0; $i < count($eraNodes) - 1; $i++) {
                $from = $eraNodes[$i]['id'];
                $to   = $eraNodes[$i + 1]['id'];
                $key  = "{$from}→{$to}";
                $edges[$key] = [
                    'from_node_id' => $from,
                    'to_node_id'   => $to,
                    'edge_type'    => 'SEQUENTIAL_DIRECT',
                    'score'        => $this->computeSequentialScore($eraNodes[$i], $eraNodes[$i + 1]),
                    'priority'     => 1,
                    'evidence_note'=> null,
                ];
            }
        }

        // 2. Era transition edges (last of era → first of next era)
        // Sort eras in canonical biblical chronological order so transition edges point forward in time.
        $eraSet = array_unique(array_column($nodes, 'era_slug'));
        usort($eraSet, function ($a, $b) {
            return (self::ERA_CANONICAL_ORDER[$a] ?? 999) <=> (self::ERA_CANONICAL_ORDER[$b] ?? 999);
        });
        $eras = array_values($eraSet);
        foreach ($eras as $i => $era) {
            if (! isset($eras[$i + 1])) continue;
            $lastOfEra  = $this->lastNodeInEra($nodes, $era);
            $firstOfNext= $this->firstNodeInEra($nodes, $eras[$i + 1]);
            if ($lastOfEra && $firstOfNext && $lastOfEra !== $firstOfNext) {
                $key = "{$lastOfEra}→{$firstOfNext}";
                $edges[$key] = [
                    'from_node_id' => $lastOfEra,
                    'to_node_id'   => $firstOfNext,
                    'edge_type'    => 'SEQUENTIAL_DIRECT',
                    'score'        => 0.70,
                    'priority'     => 1,
                    'evidence_note'=> 'Era transition',
                ];
            }
        }

        // 3. Parallel / thematic edges from ParallelLink table
        $links = ParallelLink::with(['sourceBlock.crs', 'targetBlock.crs'])
            ->where('approved', true)
            ->get();

        foreach ($links as $link) {
            $sourceCrsId = $link->sourceBlock?->crs_id;
            $targetCrsId = $link->targetBlock?->crs_id;

            if (! $sourceCrsId || ! $targetCrsId) continue;
            if (! isset($nodes[$sourceCrsId], $nodes[$targetCrsId])) continue;
            if ($sourceCrsId === $targetCrsId) continue;

            $key = "{$sourceCrsId}→{$targetCrsId}_LINK";
            if (isset($edges[$key])) continue;

            $edges[$key] = [
                'from_node_id' => $sourceCrsId,
                'to_node_id'   => $targetCrsId,
                'edge_type'    => $link->relation_type,
                'score'        => $this->computeLinkScore($link, $nodes[$sourceCrsId], $nodes[$targetCrsId]),
                'priority'     => 2,
                'evidence_note'=> $link->evidence_note,
            ];
        }

        return $edges;
    }

    // ─── Hard constraints ─────────────────────────

    private function applyHardConstraints(array &$nodes, array &$edges): void
    {
        // Remove edges to blocked nodes
        foreach ($edges as $key => $edge) {
            $from = $nodes[$edge['from_node_id']] ?? null;
            $to   = $nodes[$edge['to_node_id']] ?? null;
            if (! $from || ! $to) {
                unset($edges[$key]);
                continue;
            }
            if ($from['review_status'] === 'blocked' || $to['review_status'] === 'blocked') {
                unset($edges[$key]);
                $this->warnings[] = "Edge removed due to blocked node: {$from['source_map']} → {$to['source_map']}";
            }
        }

        // Nodes with 'especulativa' placement get reference_only display mode
        foreach ($nodes as $id => &$node) {
            if ($node['confidence'] === 'especulativa') {
                $node['display_mode'] = 'reference_only';
            }
        }
        unset($node);
    }

    // ─── Cycle detection (DFS) ───────────────────

    private function detectCycles(array $nodes, array $edges): array
    {
        $adj = [];
        foreach ($edges as $edge) {
            $adj[$edge['from_node_id']][] = $edge['to_node_id'];
        }

        $visited  = [];
        $inStack  = [];
        $cycles   = [];

        $dfs = function (int $nodeId, array $path) use (&$dfs, &$adj, &$visited, &$inStack, &$cycles, $nodes): void {
            $visited[$nodeId]  = true;
            $inStack[$nodeId]  = true;
            $path[]            = $nodes[$nodeId]['source_map'] ?? $nodeId;

            foreach ($adj[$nodeId] ?? [] as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $dfs($neighbor, $path);
                } elseif ($inStack[$neighbor] ?? false) {
                    // Found cycle — record from the cycle start
                    $start = array_search($nodes[$neighbor]['source_map'] ?? $neighbor, $path);
                    if ($start !== false) {
                        $cycles[] = array_slice($path, $start);
                    }
                }
            }

            $inStack[$nodeId] = false;
        };

        foreach (array_keys($nodes) as $id) {
            if (! isset($visited[$id])) {
                $dfs($id, []);
            }
        }

        return $cycles;
    }

    private function breakCycles(array $nodes, array $edges, array $cycles): array
    {
        // Remove the lowest-score edge actually on each cycle's path (adjacent pairs only —
        // not any edge between two nodes that merely co-occur in an overlapping cycle, which
        // previously let this strip legitimate SEQUENTIAL_DIRECT edges and orphan main-stream
        // nodes). Prefer breaking a non-sequential (thematic/parallel) edge so the main
        // forward reading path is never the one severed; a cycle can only exist because a
        // non-sequential edge points backward relative to the forward chain, so a sequential
        // edge should never need to be removed in practice.
        foreach ($cycles as $cycle) {
            $n = count($cycle);
            $candidates = [];
            for ($i = 0; $i < $n; $i++) {
                $fromMap = $cycle[$i];
                $toMap   = $cycle[($i + 1) % $n];
                foreach ($edges as $key => $edge) {
                    $eFromMap = $nodes[$edge['from_node_id']]['source_map'] ?? '';
                    $eToMap   = $nodes[$edge['to_node_id']]['source_map'] ?? '';
                    if ($eFromMap === $fromMap && $eToMap === $toMap) {
                        $candidates[$key] = $edge;
                    }
                }
            }

            if (empty($candidates)) continue;

            $nonSequential = array_filter($candidates, fn($e) => $e['edge_type'] !== 'SEQUENTIAL_DIRECT');
            $pool = $nonSequential ?: $candidates;

            $lowestScore = PHP_FLOAT_MAX;
            $lowestKey   = null;
            foreach ($pool as $key => $edge) {
                if ($edge['score'] < $lowestScore) {
                    $lowestScore = $edge['score'];
                    $lowestKey   = $key;
                }
            }

            if ($lowestKey) {
                $e = $edges[$lowestKey];
                $this->warnings[] = "Cycle broken: removed edge {$nodes[$e['from_node_id']]['source_map']} → {$nodes[$e['to_node_id']]['source_map']} (score={$lowestScore})";
                unset($edges[$lowestKey]);
            }
        }

        return $edges;
    }

    // ─── Topological sort (Kahn's algorithm) ─────

    private function topologicalOrder(array $nodes, array $edges): array
    {
        $inDegree = array_fill_keys(array_keys($nodes), 0);
        $adj      = array_fill_keys(array_keys($nodes), []);

        foreach ($edges as $edge) {
            if (! isset($inDegree[$edge['to_node_id']])) continue;
            $inDegree[$edge['to_node_id']]++;
            $adj[$edge['from_node_id']][] = $edge['to_node_id'];
        }

        $eraOrder = function (int $nodeId) use ($nodes): int {
            return self::ERA_CANONICAL_ORDER[$nodes[$nodeId]['era_slug'] ?? ''] ?? 999;
        };
        $nodeSort = function (int $a, int $b) use ($nodes, $eraOrder): int {
            $ea = $eraOrder($a);
            $eb = $eraOrder($b);
            if ($ea !== $eb) return $ea <=> $eb;
            return ($nodes[$a]['sort_key'] ?? 0) <=> ($nodes[$b]['sort_key'] ?? 0);
        };

        // Start with nodes that have no incoming edges, sorted by era then sort_key
        $queue = array_keys(array_filter($inDegree, fn($d) => $d === 0));
        usort($queue, $nodeSort);

        $ordered = [];
        while (! empty($queue)) {
            $current   = array_shift($queue);
            $ordered[] = $current;

            $neighbors = $adj[$current] ?? [];
            usort($neighbors, $nodeSort);

            foreach ($neighbors as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    // Insert maintaining era + sort_key order
                    $inserted = false;
                    foreach ($queue as $i => $q) {
                        if ($nodeSort($neighbor, $q) < 0) {
                            array_splice($queue, $i, 0, [$neighbor]);
                            $inserted = true;
                            break;
                        }
                    }
                    if (! $inserted) $queue[] = $neighbor;
                }
            }
        }

        // Nodes not reached (disconnected) — append in era + sort_key order
        $notReached = array_diff(array_keys($nodes), $ordered);
        if (! empty($notReached)) {
            $this->warnings[] = count($notReached) . " disconnected node(s) appended at end of plan.";
            usort($notReached, $nodeSort);
            $ordered = array_merge($ordered, $notReached);
        }

        return $ordered;
    }

    // ─── Persist to DB ────────────────────────────

    private function persist(array $nodes, array $edges): StreamPlan
    {
        $snapshot = LedgerSnapshot::where('status', 'imported')->latest('imported_at')->first();

        return DB::transaction(function () use ($nodes, $edges, $snapshot) {
            // Invalidate previous published plans with same profile
            StreamPlan::where('profile_id', $this->profileId)
                ->where('locale', $this->locale)
                ->where('publication_status', 'published')
                ->update(['publication_status' => 'archived']);

            $plan = StreamPlan::create([
                'profile_id'            => $this->profileId,
                'locale'                => $this->locale,
                'ledger_snapshot_id'    => $snapshot?->snapshot_id,
                'publication_status'    => 'published',
                'node_count'            => count($nodes),
                'edge_count'            => count($edges),
                'compilation_warnings'  => $this->warnings,
                'compilation_errors'    => $this->errors,
                'validation_hash'       => $this->computeHash($nodes, $edges),
                'published_at'          => now(),
            ]);

            // Persist nodes
            $nodeIdMap = []; // crs_id → StreamPlanNode id
            foreach ($nodes as $crsId => $node) {
                $planNode = StreamPlanNode::create([
                    'plan_id'              => $plan->id,
                    'crs_id'               => $node['crs_id'],
                    'rank'                 => $node['rank'],
                    'display_mode'         => $node['display_mode'],
                    'required_state'       => $node['required_state'],
                    'stream_role'          => $node['stream_role'],
                    'user_facing_era'      => $node['user_facing_era'],
                    'user_facing_era_sort' => $node['user_facing_era_sort'],
                    'is_main_stream_node'  => $node['is_main_stream_node'],
                ]);
                $nodeIdMap[$crsId] = $planNode->id;
            }

            // Persist edges. Different edge sources (sequential era-adjacency vs.
            // approved ParallelLinks) can independently resolve to the same
            // (from, to) node pair; stream_plan_edges has a unique constraint on
            // that pair, so keep only the first edge seen for a given pair.
            $seenPairs = [];
            foreach ($edges as $edge) {
                $fromPlanNodeId = $nodeIdMap[$edge['from_node_id']] ?? null;
                $toPlanNodeId   = $nodeIdMap[$edge['to_node_id']] ?? null;
                if (! $fromPlanNodeId || ! $toPlanNodeId) continue;

                $pairKey = "{$fromPlanNodeId}-{$toPlanNodeId}";
                if (isset($seenPairs[$pairKey])) continue;
                $seenPairs[$pairKey] = true;

                StreamPlanEdge::create([
                    'plan_id'      => $plan->id,
                    'from_node_id' => $fromPlanNodeId,
                    'to_node_id'   => $toPlanNodeId,
                    'edge_type'    => $edge['edge_type'],
                    'score'        => $edge['score'],
                    'priority'     => $edge['priority'],
                    'evidence_note'=> $edge['evidence_note'],
                ]);
            }

            return $plan;
        });
    }

    // ─── Score helpers ────────────────────────────

    private function computeSequentialScore(array $from, array $to): float
    {
        $evidence  = self::CONFIDENCE_SCORE[$from['confidence']] ?? 0.5;
        $relation  = self::CONFIDENCE_SCORE[$to['confidence']] ?? 0.5;
        $placement = ($from['sort_key'] > 0 && $to['sort_key'] > $from['sort_key']) ? 1.0 : 0.5;
        $review    = self::REVIEW_SCORE[$from['review_status']] ?? 0.5;

        return round(
            ($evidence  * self::W_EVIDENCE)  +
            ($relation  * self::W_RELATION)  +
            ($placement * self::W_PLACEMENT) +
            ($review    * self::W_REVIEW)    +
            (0.8        * self::W_PROFILE),  // profile bonus for sequential
            4
        );
    }

    private function computeLinkScore(ParallelLink $link, array $from, array $to): float
    {
        $evidence  = self::CONFIDENCE_SCORE[$link->confidence] ?? 0.5;
        $relation  = self::CONFIDENCE_SCORE[$to['confidence']] ?? 0.5;
        $placement = self::CONFIDENCE_SCORE[$from['confidence']] ?? 0.5;
        $review    = self::REVIEW_SCORE[$from['review_status']] ?? 0.5;
        $profile   = $link->approved ? 1.0 : 0.4;

        return round(
            ($evidence  * self::W_EVIDENCE)  +
            ($relation  * self::W_RELATION)  +
            ($placement * self::W_PLACEMENT) +
            ($review    * self::W_REVIEW)    +
            ($profile   * self::W_PROFILE),
            4
        );
    }

    // ─── Display/state resolvers ──────────────────

    private function resolveDisplayMode(ChronologicalReadingSet $crs): string
    {
        // CRS-level hint takes precedence (e.g. unresolved_prophetic_window for Joel)
        if (! empty($crs->display_mode)) {
            return $crs->display_mode;
        }

        // Check for verse-range blocks (new system) or legacy passage_id
        $hasText = $crs->blocks()->where(function ($q) {
            $q->whereNotNull('start_book_id')->orWhereNotNull('passage_id');
        })->exists();

        if (! $hasText) {
            return 'reference_only';
        }
        // Speculative placements get reference_only per addendum §6
        if ($crs->placement_confidence === 'especulativa') {
            return 'reference_only';
        }
        return 'full';
    }

    private function resolveRequiredState(ChronologicalReadingSet $crs): string
    {
        // Only narrative_anchor required; others optional in Narrative Flow
        return 'none';
    }

    // ─── Utility ─────────────────────────────────

    private function firstNodeInEra(array $nodes, string $eraSlug): ?int
    {
        $era = array_filter($nodes, fn($n) => $n['era_slug'] === $eraSlug);
        if (empty($era)) return null;
        usort($era, fn($a, $b) => $a['sort_key'] <=> $b['sort_key']);
        return array_values($era)[0]['id'];
    }

    private function lastNodeInEra(array $nodes, string $eraSlug): ?int
    {
        $era = array_filter($nodes, fn($n) => $n['era_slug'] === $eraSlug);
        if (empty($era)) return null;
        usort($era, fn($a, $b) => $a['sort_key'] <=> $b['sort_key']);
        return array_values($era)[count($era) - 1]['id'];
    }

    private function computeHash(array $nodes, array $edges): string
    {
        $data = [
            'profile'    => $this->profileId,
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'node_maps'  => array_column($nodes, 'source_map'),
        ];
        return hash('sha256', json_encode($data));
    }

    public function getWarnings(): array { return $this->warnings; }
    public function getErrors(): array   { return $this->errors; }
}
