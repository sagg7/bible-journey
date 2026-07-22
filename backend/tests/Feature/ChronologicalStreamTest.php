<?php

namespace Tests\Feature;

use App\Models\StreamPlan;
use App\Models\StreamPlanNode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChronologicalStreamTest extends TestCase
{
    // Tests run against the real DB (no fake data) so we don't use RefreshDatabase.
    // They assert structural invariants of the published plan.

    private function activePlan(): StreamPlan
    {
        $plan = StreamPlan::latestPublished('cautious_default', 'es');
        $this->assertNotNull($plan, 'No published plan found — run php artisan harmonize:compile first');
        return $plan;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Chronological endpoint must not contain forbidden internal-metadata eras
    // ──────────────────────────────────────────────────────────────────────────
    public function test_chronological_eras_contain_no_internal_metadata_headers(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $eraTitles = collect($response->json('eras'))->pluck('title')->all();

        $forbidden = [
            'Retrospectiva post-exílica',
            'Marco editorial del Salterio',
            'Ventana davídica',
            'Tradición mosaica',
            'Colección del Salterio',
            'Tradición davídica y de peregrinación',
            'Colecciones poéticas del templo y comunitarias',
            'Colección poética davídica',
            'Retrospectiva genealógica',
            'Ventana salomónica',
            'Tradición sapiencial salomónica',
            'Literatura sapiencial / cronología sin resolver',
            'Alianza davídica y tradición del templo',
            'Peregrinación y memoria del exilio',
            'Conclusión del Salterio',
            'Colecciones poéticas davídicas y del templo',
        ];

        foreach ($forbidden as $header) {
            $this->assertNotContains($header, $eraTitles,
                "Forbidden internal header '{$header}' appeared in chronological stream.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. A CRS with stream_role = editorial_context must not appear as main node
    // ──────────────────────────────────────────────────────────────────────────
    public function test_editorial_context_nodes_are_not_main_stream(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $mainRoles = collect($response->json('eras'))
            ->flatMap(fn($era) => $era['nodes'])
            ->pluck('stream_role')
            ->unique()
            ->all();

        $this->assertNotContains('editorial_context', $mainRoles,
            'editorial_context nodes must not appear in chronological stream.');
        $this->assertNotContains('composition_context', $mainRoles,
            'composition_context nodes must not appear in chronological stream.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. A CRS with stream_role = literary_collection must not create a new era
    // ──────────────────────────────────────────────────────────────────────────
    public function test_literary_collection_nodes_do_not_create_new_eras(): void
    {
        $plan = $this->activePlan();

        // Ensure these eras do NOT appear as top-level era titles
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $eraTitles = collect($response->json('eras'))->pluck('title')->all();

        // literary_collection nodes are folded under parent eras, not standalone
        $this->assertNotContains('Colección poética davídica', $eraTitles);
        $this->assertNotContains('Literatura sapiencial / cronología sin resolver', $eraTitles);
        $this->assertNotContains('Colección del Salterio', $eraTitles);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. David-era Psalms appear as associated_poetry with correct confidence
    // ──────────────────────────────────────────────────────────────────────────
    public function test_david_psalm_crs_have_stream_roles_for_poetry(): void
    {
        $davidPsalmSlug = 'rise-of-the-monarchy';

        // CRS-1SA-009 to CRS-1SA-014 have Psalms in their blocks. Their
        // stream_role is main_historical_event (event WITH psalm included).
        // Standalone psalm collections must be associated_poetry or literary_collection.
        $standalonePoetry = \App\Models\ChronologicalReadingSet::whereIn('era_slug', [
            'davidic-poetry-collection',
            'davidic-window',
            'davidic-pilgrimage-tradition',
        ])->get();

        foreach ($standalonePoetry as $crs) {
            $this->assertContains(
                $crs->stream_role,
                ['associated_poetry', 'literary_collection'],
                "CRS {$crs->source_map} should be associated_poetry or literary_collection"
            );
            $this->assertFalse(
                (bool) $crs->is_main_stream_node,
                "CRS {$crs->source_map} should not be a main stream node"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. No era jump from editorial metadata back to David / monarchy without
    //    passing through a historical transition
    // ──────────────────────────────────────────────────────────────────────────
    public function test_era_order_is_monotonically_non_decreasing(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $eras = collect($response->json('eras'));
        $prevSort = 0;
        foreach ($eras as $era) {
            $sort = $era['user_facing_era_sort'] ?? 0;
            $this->assertGreaterThanOrEqual($prevSort, $sort,
                "Era '{$era['title']}' (sort={$sort}) is before previous era (sort={$prevSort}) — broken ordering.");
            $prevSort = $sort;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. The plan contains at least the expected main-stream node counts
    // ──────────────────────────────────────────────────────────────────────────
    public function test_main_stream_has_expected_era_counts(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $erasByTitle = collect($response->json('eras'))->keyBy('title');

        $expected = [
            'Los patriarcas'                            => 7,
            'El surgimiento de la monarquía'            => 28,
            'Monarquía unida'                           => 55,
            'El exilio y la esperanza del retorno'      => 2,
            'La vida de Jesús'                          => 90,
        ];

        foreach ($expected as $title => $count) {
            $this->assertArrayHasKey($title, $erasByTitle->all(), "Era '{$title}' missing from chronological stream.");
            $actualCount = count($erasByTitle[$title]['nodes']);
            $this->assertEquals($count, $actualCount,
                "Era '{$title}' has {$actualCount} nodes, expected {$count}.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Canonical Reading is structurally independent: biblical_books table
    //    has no stream_role or user_facing_era columns (no contamination)
    // ──────────────────────────────────────────────────────────────────────────
    public function test_canonical_reading_structure_is_independent_of_stream_plan(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('biblical_books');
        $this->assertNotContains('stream_role', $columns,
            'biblical_books must not have stream_role — canonical reading is independent of stream plan.');
        $this->assertNotContains('user_facing_era', $columns,
            'biblical_books must not have user_facing_era — canonical reading is independent of stream plan.');

        // Also verify stream plan nodes do NOT reference biblical_books directly
        $spnColumns = \Illuminate\Support\Facades\Schema::getColumnListing('stream_plan_nodes');
        $this->assertNotContains('biblical_book_id', $spnColumns,
            'stream_plan_nodes must not reference biblical_books directly.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. DB-level: no CRS left without classification
    // ──────────────────────────────────────────────────────────────────────────
    public function test_all_crs_have_stream_role_assigned(): void
    {
        $unclassified = \App\Models\ChronologicalReadingSet::whereNull('stream_role')->count();
        $this->assertEquals(0, $unclassified,
            "{$unclassified} CRS entries still have NULL stream_role.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. No OT CRS with stream_role = epistolary_context (AT ≠ NT epistolary era)
    // ──────────────────────────────────────────────────────────────────────────
    public function test_no_ot_crs_has_epistolary_context_role(): void
    {
        $contaminated = \App\Models\ChronologicalReadingSet::where('stream_role', 'epistolary_context')
            ->whereHas('blocks', function ($q) {
                $q->whereHas('startBook', fn($b) => $b->where('testament', 'OT'));
            })
            ->count();

        $this->assertEquals(0, $contaminated,
            "{$contaminated} OT-containing CRS are classified as epistolary_context — AT content must never appear under NT epistolary era.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. Correspondence-beyond-acts are not main stream nodes
    // ──────────────────────────────────────────────────────────────────────────
    public function test_correspondence_beyond_acts_are_not_main_stream_nodes(): void
    {
        $leaked = \App\Models\ChronologicalReadingSet::where('era_slug', 'correspondence-beyond-acts')
            ->where('is_main_stream_node', true)
            ->count();

        $this->assertEquals(0, $leaked,
            "{$leaked} correspondence-beyond-acts CRS are still main stream nodes. These pair NT epistles with Genesis passages and have no secure Acts anchor.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. Isaiah 40-55 is in "El exilio y la esperanza del retorno"
    // ──────────────────────────────────────────────────────────────────────────
    public function test_isaiah_40_55_is_in_exile_hope_era(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $erasByTitle = collect($response->json('eras'))->keyBy('title');

        $this->assertArrayHasKey('El exilio y la esperanza del retorno', $erasByTitle->all(),
            'Era "El exilio y la esperanza del retorno" is missing — Isaiah 40-55 has no era.');

        $nodeRefs = collect($erasByTitle['El exilio y la esperanza del retorno']['nodes'])
            ->pluck('reference')
            ->all();

        $hasIsaiah40 = collect($nodeRefs)->contains(fn($ref) => str_contains((string) $ref, 'Isaías 40'));
        $this->assertTrue($hasIsaiah40,
            'Isaiah 40+ not found in "El exilio y la esperanza del retorno". Nodes: ' . implode(', ', $nodeRefs));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. Isaiah 56-66 is in "El retorno y la reconstrucción", NOT in "El exilio"
    // ──────────────────────────────────────────────────────────────────────────
    public function test_isaiah_56_66_is_in_return_era_not_exile(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $erasByTitle = collect($response->json('eras'))->keyBy('title');

        // Must appear in El retorno
        $this->assertArrayHasKey('El retorno y la reconstrucción', $erasByTitle->all());
        $retornoRefs = collect($erasByTitle['El retorno y la reconstrucción']['nodes'])
            ->pluck('reference')
            ->all();
        $hasIsaiah56 = collect($retornoRefs)->contains(fn($ref) => str_contains((string) $ref, 'Isaías 56'));
        $this->assertTrue($hasIsaiah56,
            'Isaiah 56+ not found in "El retorno y la reconstrucción". Nodes: ' . implode(', ', $retornoRefs));

        // Must NOT appear in El exilio
        if (isset($erasByTitle['El exilio'])) {
            $exilioRefs = collect($erasByTitle['El exilio']['nodes'])
                ->pluck('reference')
                ->all();
            $hasIsaiah56InExilio = collect($exilioRefs)->contains(fn($ref) =>
                preg_match('/Isaías 5[6-9]|Isaías 6[0-6]/', (string) $ref)
            );
            $this->assertFalse($hasIsaiah56InExilio,
                'Isaiah 56-66 still appears in "El exilio" — split not applied.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. Joel has null user_facing_era and is not a main stream node
    // ──────────────────────────────────────────────────────────────────────────
    public function test_joel_has_null_era_and_unresolved_display_mode(): void
    {
        $joel = \App\Models\ChronologicalReadingSet::where('era_slug', 'chronological-placement-unresolved')
            ->where('source_map', 'like', '%JOEL%')
            ->orWhere(fn($q) => $q->whereHas('blocks', function ($bq) {
                $bq->whereHas('startBook', fn($b) => $b->where('name_es', 'Joel'));
            }))
            ->where('era_slug', 'chronological-placement-unresolved')
            ->first();

        if (! $joel) {
            // Fallback: find by era_slug directly
            $joel = \App\Models\ChronologicalReadingSet::where('era_slug', 'chronological-placement-unresolved')->first();
        }

        $this->assertNotNull($joel, 'Joel CRS (chronological-placement-unresolved) not found.');
        $this->assertNull($joel->user_facing_era,
            "Joel must have user_facing_era = NULL, got '{$joel->user_facing_era}'.");
        $this->assertFalse((bool) $joel->is_main_stream_node,
            'Joel must not be a main stream node.');
        $this->assertEquals('unresolved_prophetic_window', $joel->display_mode,
            "Joel must have display_mode = 'unresolved_prophetic_window', got '{$joel->display_mode}'.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. No Genesis CRS appears as a main node in any NT era (sort >= 200)
    // ──────────────────────────────────────────────────────────────────────────
    public function test_no_genesis_passage_in_nt_era(): void
    {
        $plan = $this->activePlan();

        $genesisInNt = \App\Models\StreamPlanNode::where('plan_id', $plan->id)
            ->where('is_main_stream_node', true)
            ->where('user_facing_era_sort', '>=', 200)
            ->whereHas('crs.blocks', function ($q) {
                $q->whereHas('startBook', fn($b) => $b->where('name_es', 'Génesis'));
            })
            ->count();

        $this->assertEquals(0, $genesisInNt,
            "{$genesisInNt} nodes containing Genesis passages appear as main stream nodes in NT eras (sort >= 200). AT content must not contaminate NT eras.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 15. Every is_main_stream_node = true CRS has a non-null user_facing_era
    // ──────────────────────────────────────────────────────────────────────────
    public function test_all_main_stream_nodes_have_user_facing_era(): void
    {
        $orphaned = \App\Models\ChronologicalReadingSet::where('is_main_stream_node', true)
            ->whereNull('user_facing_era')
            ->count();

        $this->assertEquals(0, $orphaned,
            "{$orphaned} main stream CRS have null user_facing_era — every visible node needs an era header.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 16. Isaiah split era order: exile hope (105) < return (110) in API response
    // ──────────────────────────────────────────────────────────────────────────
    public function test_isaiah_split_eras_appear_in_correct_order(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $eras = collect($response->json('eras'));
        $exileHopeSort  = $eras->firstWhere('title', 'El exilio y la esperanza del retorno')['user_facing_era_sort'] ?? null;
        $returnSort     = $eras->firstWhere('title', 'El retorno y la reconstrucción')['user_facing_era_sort'] ?? null;

        $this->assertNotNull($exileHopeSort, 'Era "El exilio y la esperanza del retorno" missing from API.');
        $this->assertNotNull($returnSort,    'Era "El retorno y la reconstrucción" missing from API.');
        $this->assertLessThan($returnSort, $exileHopeSort,
            "Era sort order wrong: exile hope ({$exileHopeSort}) must come before return ({$returnSort}).");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 17. Genesis 1–11 appears under "Los primeros tiempos"
    // ──────────────────────────────────────────────────────────────────────────
    public function test_genesis_1_11_appears_under_primeros_tiempos(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $erasByTitle = collect($response->json('eras'))->keyBy('title');

        $this->assertArrayHasKey('Los primeros tiempos', $erasByTitle->all(),
            'Era "Los primeros tiempos" is missing — Genesis 1-11 has no dedicated era.');

        $nodeRefs = collect($erasByTitle['Los primeros tiempos']['nodes'])
            ->pluck('reference')
            ->all();

        $hasGen1 = collect($nodeRefs)->contains(fn($ref) =>
            preg_match('/Génesis [1-9]\b/', (string) $ref)
        );
        $this->assertTrue($hasGen1,
            'Genesis 1-9 range not found in "Los primeros tiempos". Refs: ' . implode(', ', $nodeRefs));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 18. Genesis 12–50 is in "Los patriarcas", and Genesis 1–11 is NOT
    // ──────────────────────────────────────────────────────────────────────────
    public function test_genesis_12_50_in_patriarcas_not_genesis_1_11(): void
    {
        $plan = $this->activePlan();
        $response = $this->getJson("/api/v2/stream-plans/{$plan->id}/chronological");
        $response->assertStatus(200);

        $erasByTitle = collect($response->json('eras'))->keyBy('title');

        $this->assertArrayHasKey('Los patriarcas', $erasByTitle->all(),
            'Era "Los patriarcas" is missing from chronological stream.');

        $patriarcaRefs = collect($erasByTitle['Los patriarcas']['nodes'])
            ->pluck('reference')
            ->all();

        $hasGen12Plus = collect($patriarcaRefs)->contains(fn($ref) =>
            str_contains((string) $ref, 'Génesis 12') || preg_match('/Génesis [2-4]\d/', (string) $ref)
        );
        $this->assertTrue($hasGen12Plus,
            'Genesis 12+ not found in "Los patriarcas". Refs: ' . implode(', ', $patriarcaRefs));

        $hasGen1to11 = collect($patriarcaRefs)->contains(function ($ref) {
            if (preg_match('/Génesis (\d+)/', (string) $ref, $m)) {
                return (int) $m[1] <= 11;
            }
            return false;
        });
        $this->assertFalse($hasGen1to11,
            'Genesis 1-11 found in "Los patriarcas" — must only appear in "Los primeros tiempos". Refs: ' . implode(', ', $patriarcaRefs));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 19. A historical_bridge is visible between "El retorno" (sort 110) and
    //     "La vida de Jesús" (sort 200)
    // ──────────────────────────────────────────────────────────────────────────
    public function test_intertestamental_bridge_exists_between_retorno_and_jesus(): void
    {
        $bridge = \App\Models\ChronologicalReadingSet::where('stream_role', 'historical_bridge')
            ->whereBetween('user_facing_era_sort', [111, 199])
            ->first();

        $this->assertNotNull($bridge,
            'No historical_bridge CRS found with user_facing_era_sort between 111 and 199. A visible intertestamental bridge is required between "El retorno" and "La vida de Jesús".');

        $this->assertEquals('historical_bridge', $bridge->display_mode,
            "Intertestamental bridge must have display_mode='historical_bridge', got '{$bridge->display_mode}'.");

        $this->assertTrue((bool) $bridge->is_main_stream_node,
            "Intertestamental bridge '{$bridge->source_map}' must be is_main_stream_node=true to be visible in the Stream.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 20. The intertestamental bridge has zero reading blocks — it does not
    //     count as a biblical chapter read
    // ──────────────────────────────────────────────────────────────────────────
    public function test_intertestamental_bridge_has_no_reading_blocks(): void
    {
        $bridges = \App\Models\ChronologicalReadingSet::where('stream_role', 'historical_bridge')->get();

        $this->assertNotEmpty($bridges->all(), 'No historical_bridge CRS found.');

        foreach ($bridges as $bridge) {
            $blockCount = \App\Models\ReadingBlock::where('crs_id', $bridge->id)->count();
            $this->assertEquals(0, $blockCount,
                "Historical bridge '{$bridge->source_map}' has {$blockCount} reading blocks. Bridges must have 0 blocks — they must not count toward biblical reading progress.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 21. The coverage_paths table has exactly 1,189 rows for the active plan
    //     (one per biblical chapter — including uncovered chapters)
    // ──────────────────────────────────────────────────────────────────────────
    public function test_coverage_paths_has_exactly_1189_rows_for_active_plan(): void
    {
        $plan = $this->activePlan();

        $total = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)->count();

        $this->assertEquals(1189, $total,
            "Expected exactly 1,189 coverage paths (one per biblical chapter), got {$total}. Run 'php artisan coverage:build' to refresh, then rerun this test.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 22. Every reachable coverage path has a non-null parent_era and
    //     entry_point_node_id; unreachable paths are explicitly flagged 'uncovered'
    // ──────────────────────────────────────────────────────────────────────────
    public function test_reachable_paths_have_valid_era_and_entry_point(): void
    {
        $plan = $this->activePlan();

        // Unresolved prophetic windows (e.g. Joel) are reachable but intentionally have no parent era
        $missingParentEra = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('is_user_reachable', true)
            ->whereNull('parent_era')
            ->where('display_mode', '!=', 'unresolved_prophetic_window')
            ->count();

        $missingEntryPoint = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('is_user_reachable', true)
            ->whereNull('entry_point_node_id')
            ->count();

        $this->assertEquals(0, $missingParentEra,
            "{$missingParentEra} reachable coverage paths are missing parent_era.");
        $this->assertEquals(0, $missingEntryPoint,
            "{$missingEntryPoint} reachable coverage paths are missing entry_point_node_id.");

        // Unreachable paths must be explicitly flagged — no silent gaps
        $silentGaps = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('is_user_reachable', false)
            ->where('display_mode', '!=', 'uncovered')
            ->count();

        $this->assertEquals(0, $silentGaps,
            "{$silentGaps} non-reachable paths are not flagged as 'uncovered'. Unreachable chapters must use display_mode='uncovered'.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 23. No hidden CRS is required in Complete Chronological Reading without
    //     a navigable entry_point_node_id
    // ──────────────────────────────────────────────────────────────────────────
    public function test_no_hidden_crs_required_for_ccr_without_navigation_path(): void
    {
        $plan = $this->activePlan();

        $stranded = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('complete_mode_required', true)
            ->where('is_user_reachable', false)
            ->count();

        $this->assertEquals(0, $stranded,
            "{$stranded} chapters are complete_mode_required but have no reachable coverage path. " .
            "A hidden CRS must always provide a navigable entry_point_node_id — no chapter required for CCR can be stranded.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 24. Complete Chronological Reading can traverse all required blocks —
    //     every CRS with required blocks is present in the active stream plan
    // ──────────────────────────────────────────────────────────────────────────
    public function test_complete_chronological_reading_can_traverse_all_required_blocks(): void
    {
        $plan = $this->activePlan();

        $crsWithRequiredBlocks = \App\Models\ReadingBlock::where('required_in_complete_mode', true)
            ->pluck('crs_id')
            ->unique();

        $crsInPlan = \App\Models\StreamPlanNode::where('plan_id', $plan->id)
            ->pluck('crs_id')
            ->unique();

        $missingFromPlan = $crsWithRequiredBlocks->diff($crsInPlan);

        $this->assertEquals(0, $missingFromPlan->count(),
            $missingFromPlan->count() . " CRS with required_in_complete_mode blocks are not present in Stream Plan #{$plan->id}. " .
            "CCR cannot be completed without them. Missing CRS IDs: " . $missingFromPlan->take(10)->implode(', '));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 25. Narrative Flow records literary windows as 'pending' (recoverable)
    //     so Narrative Flow can continue without completing them immediately
    // ──────────────────────────────────────────────────────────────────────────
    public function test_narrative_flow_records_pending_literary_windows(): void
    {
        $plan = $this->activePlan();

        $literaryWindowCount = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('display_mode', 'literary_window')
            ->count();

        $this->assertGreaterThan(0, $literaryWindowCount,
            'No literary_window coverage paths found for the active plan. ' .
            'literary_collection CRS must be tracked as recoverable windows in Narrative Flow.');

        $invalidBehavior = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('display_mode', 'literary_window')
            ->whereNotIn('narrative_flow_behavior', ['pending', 'optional'])
            ->count();

        $this->assertEquals(0, $invalidBehavior,
            "{$invalidBehavior} literary_window paths have invalid narrative_flow_behavior. " .
            "Literary windows must be 'pending' or 'optional' so Narrative Flow can skip and recover them.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 26. Canonical Reading does not expose stream event counts as a metric
    //     — biblical_books table has no node_count / stream_plan_id columns
    // ──────────────────────────────────────────────────────────────────────────
    public function test_canonical_reading_does_not_expose_stream_event_counts(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('biblical_books');

        $this->assertNotContains('node_count', $columns,
            'biblical_books must not have a node_count column — this is a stream architecture metric, not a canonical reading metric.');
        $this->assertNotContains('stream_plan_id', $columns,
            'biblical_books must not reference a stream_plan_id — canonical reading is plan-independent.');
        $this->assertNotContains('crs_count', $columns,
            'biblical_books must not have a crs_count column — canonical reading structure is Libro → capítulos → Reader, not Libro → acontecimiento count.');

        // Stream plan nodes must not carry canonical_chapter_count
        $spnColumns = \Illuminate\Support\Facades\Schema::getColumnListing('stream_plan_nodes');
        $this->assertNotContains('canonical_chapter_count', $spnColumns,
            'stream_plan_nodes must not expose canonical_chapter_count — that is a reading architecture concern, not a stream plan concern.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Tests 27–36 — Complete Mode Coverage (Plan 9.1)
    //
    // These tests target Plan 9.1 (version='9.1'), which fixes the 249-chapter
    // gap between canonical coverage and complete-mode coverage.
    // They are skipped if Plan 9.1 has not been compiled yet.
    // ══════════════════════════════════════════════════════════════════════════

    private function plan91(): \App\Models\StreamPlan
    {
        // Check if the version column exists (migration may not have run yet)
        $hasVersionCol = \Illuminate\Support\Facades\Schema::hasColumn('stream_plans', 'version');
        if (! $hasVersionCol) {
            $this->markTestSkipped(
                'Plan 9.1 not found: migration not yet run. Run: php artisan migrate'
            );
        }

        $plan = \App\Models\StreamPlan::where('version', '9.1')->first();
        if (! $plan) {
            $this->markTestSkipped(
                'Plan 9.1 not found. Run: php artisan migrate && ' .
                'mysql -u root bible_journey < manifest_plan91.sql && ' .
                'php artisan stream-plans:clone 9 --plan-version=9.1 && ' .
                'php artisan coverage:build <new_plan_id>'
            );
        }
        return $plan;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 27. Complete Chronological Reading requires exactly 1,189 chapters
    //     — no gap between canonical coverage and complete-mode coverage
    // ──────────────────────────────────────────────────────────────────────────
    public function test_complete_mode_requires_exactly_1189_chapters(): void
    {
        $plan = $this->plan91();

        $requiredTotal = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('complete_mode_required', 1)
            ->count();

        $this->assertEquals(1189, $requiredTotal,
            "Plan {$plan->id} (v{$plan->version}) has {$requiredTotal} complete_mode_required chapters. " .
            "Expected 1,189 — every canonical chapter must be required in Complete Chronological Reading.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 28. Literary window chapters carry complete_mode_required = true
    //     — being outside the main narrative does NOT exclude them from CCR
    // ──────────────────────────────────────────────────────────────────────────
    public function test_literary_window_chapters_are_complete_mode_required(): void
    {
        $plan = $this->plan91();

        $windowsNotRequired = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('display_mode', 'literary_window')
            ->where('complete_mode_required', 0)
            ->count();

        $this->assertEquals(0, $windowsNotRequired,
            "{$windowsNotRequired} literary_window chapters have complete_mode_required=false in Plan {$plan->id}. " .
            "Literary windows are required in Complete Chronological Reading.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 29. A non-main-stream node can be required in Complete Mode
    //     — is_main_stream_node=false does not imply excluded_from_complete_mode
    // ──────────────────────────────────────────────────────────────────────────
    public function test_non_main_nodes_can_be_complete_mode_required(): void
    {
        $plan = $this->plan91();

        // There must be at least some chapters covered by non-main nodes that ARE required
        $nonMainRequired = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('complete_mode_required', 1)
            ->where('display_mode', '!=', 'main_historical_event')
            ->where('display_mode', '!=', 'uncovered')
            ->count();

        $this->assertGreaterThan(0, $nonMainRequired,
            "No non-main chapters are marked complete_mode_required in Plan {$plan->id}. " .
            "Secondary nodes (poetry, windows, fallbacks) must also be required in Complete Mode.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 30. Narrative Flow can defer a literary window without marking it complete
    //     — narrative_flow_behavior for windows must be 'pending' or 'optional'
    // ──────────────────────────────────────────────────────────────────────────
    public function test_narrative_flow_defers_literary_windows_as_pending(): void
    {
        $plan = $this->plan91();

        // Windows must be deferrable (not 'included') in narrative flow
        $invalidWindowBehavior = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->whereIn('display_mode', ['literary_window', 'associated_reading', 'canonical_fallback_window'])
            ->where('narrative_flow_behavior', 'included')
            ->count();

        $this->assertEquals(0, $invalidWindowBehavior,
            "{$invalidWindowBehavior} window chapters have narrative_flow_behavior='included' in Plan {$plan->id}. " .
            "Literary windows must be 'pending' or 'optional' so Narrative Flow can continue past them.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 31. All Narrative Flow deferred chapters are recoverable
    //     — no deferred chapter can have is_user_reachable=false
    // ──────────────────────────────────────────────────────────────────────────
    public function test_all_narrative_flow_deferred_chapters_are_recoverable(): void
    {
        $plan = $this->plan91();

        $unreachableDeferred = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->whereIn('narrative_flow_behavior', ['pending', 'optional'])
            ->where('is_user_reachable', 0)
            ->count();

        $this->assertEquals(0, $unreachableDeferred,
            "{$unreachableDeferred} Narrative Flow deferred chapters are not user-reachable in Plan {$plan->id}. " .
            "Every deferred chapter must have a valid entry_point_node_id so the user can recover it.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 32. Complete Mode cannot finish at Revelation 22 with required chapters pending
    //     — Revelation 22 itself must be complete_mode_required
    // ──────────────────────────────────────────────────────────────────────────
    public function test_revelation_22_is_complete_mode_required(): void
    {
        $plan = $this->plan91();

        $revelation = \App\Models\BiblicalBook::where('name_es', 'Apocalipsis')
            ->orWhere('osis_code', 'Rev')
            ->first();

        $this->assertNotNull($revelation, 'Biblical book Apocalipsis (Rev) not found.');

        $rev22Path = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('bible_book_id', $revelation->id)
            ->where('chapter', 22)
            ->first();

        $this->assertNotNull($rev22Path,
            'Coverage path for Revelation 22 not found in Plan ' . $plan->id);

        $this->assertTrue((bool) $rev22Path->complete_mode_required,
            'Revelation 22 must be complete_mode_required=true. ' .
            'A user cannot reach "Complete Chronological Reading finished" before completing all 1,189 chapters.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 33. Joel, Hebreos, cartas generales, and Apocalipsis have valid entry points
    //     — each must have a non-null entry_point_node_id in Plan 9.1 coverage
    // ──────────────────────────────────────────────────────────────────────────
    public function test_key_books_have_valid_entry_points(): void
    {
        $plan = $this->plan91();

        $booksToCheck = ['Joel', 'Hebreos', 'Santiago', '1 Pedro', '2 Pedro',
                         '1 Juan', '2 Juan', '3 Juan', 'Judas', 'Apocalipsis'];

        foreach ($booksToCheck as $bookName) {
            $book = \App\Models\BiblicalBook::where('name_es', $bookName)->first();
            if (! $book) {
                continue; // skip if book name differs slightly in DB
            }

            $missingEntryPoint = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
                ->where('bible_book_id', $book->id)
                ->where('is_user_reachable', 1)
                ->whereNull('entry_point_node_id')
                ->count();

            $this->assertEquals(0, $missingEntryPoint,
                "{$missingEntryPoint} reachable chapters of {$bookName} have no entry_point_node_id in Plan {$plan->id}. " .
                "Every reachable chapter must have a navigable entry point.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 34. Required-window chapters must never be covered by STRICTLY-SECONDARY
    //     roles promoted to main stream. Nota (auditoría 2026-07-16): la regla
    //     original excluía solo historical_bridge, pero contradecía al test #6
    //     y al diseño real del producto — prophetic_context (ventanas de Isaías
    //     que COMPONEN las eras 'El exilio y la esperanza del retorno' /
    //     'El retorno'), canonical_fallback (epístolas, único contenido main de
    //     'Las cartas…'/'Cartas generales…') y apocalyptic_literary_sequence
    //     (Apocalipsis) son nodos main de primera clase, tal como se ve en el
    //     plan publicado en producción. La regla protege ahora los roles que sí
    //     deben permanecer secundarios (poesía, colecciones, genealogías,
    //     contextos editoriales).
    // ──────────────────────────────────────────────────────────────────────────
    public function test_required_windows_do_not_appear_as_main_stream_eras(): void
    {
        $plan = $this->plan91();

        $strictlySecondaryRoles = [
            'associated_poetry',
            'literary_collection',
            'genealogy_context',
            'editorial_context',
            'composition_context',
            'epistolary_context',
        ];

        $illegallyExposed = \Illuminate\Support\Facades\DB::table('chronological_coverage_paths as cp')
            ->join('stream_plan_nodes as spn', 'spn.id', '=', 'cp.primary_stream_plan_node_id')
            ->join('chronological_reading_sets as crs', 'crs.id', '=', 'spn.crs_id')
            ->where('cp.plan_id', $plan->id)
            ->where('cp.complete_mode_required', 1)
            ->where('cp.display_mode', '!=', 'main_historical_event')
            ->where('cp.display_mode', '!=', 'historical_bridge')
            ->where('cp.display_mode', '!=', 'uncovered')
            ->where('spn.is_main_stream_node', 1)
            ->whereIn('crs.stream_role', $strictlySecondaryRoles)
            ->count();

        $this->assertEquals(0, $illegallyExposed,
            "{$illegallyExposed} required-window chapters are covered by main-stream nodes with strictly-secondary roles. " .
            "Poetry, collections, genealogies and editorial contexts must remain secondary nodes.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 35. All complete_mode_required chapters are user-reachable in Plan 9.1
    //     — required_chapters_reachable must equal 1,189
    // ──────────────────────────────────────────────────────────────────────────
    public function test_all_complete_mode_required_chapters_are_reachable(): void
    {
        $plan = $this->plan91();

        $requiredTotal = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('complete_mode_required', 1)
            ->count();

        $requiredUnreachable = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('complete_mode_required', 1)
            ->where('is_user_reachable', 0)
            ->count();

        $this->assertEquals(1189, $requiredTotal,
            "complete_mode_required total must be 1,189, got {$requiredTotal}.");
        $this->assertEquals(0, $requiredUnreachable,
            "{$requiredUnreachable} complete_mode_required chapters are not user-reachable in Plan {$plan->id}. " .
            "Every required chapter must have a navigable path.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 36. No chapter is "canonically covered" but absent from Complete Mode
    //     — covered chapter count must equal complete_mode_required count
    // ──────────────────────────────────────────────────────────────────────────
    public function test_covered_chapters_equal_complete_mode_required_chapters(): void
    {
        $plan = $this->plan91();

        $coveredChapters = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('display_mode', '!=', 'uncovered')
            ->count();

        $requiredChapters = \App\Models\ChronologicalCoveragePath::where('plan_id', $plan->id)
            ->where('complete_mode_required', 1)
            ->count();

        $this->assertEquals($coveredChapters, $requiredChapters,
            "Mismatch between covered ({$coveredChapters}) and complete_mode_required ({$requiredChapters}) chapters in Plan {$plan->id}. " .
            "Every chapter that has an own-book ReadingBlock must also be required in Complete Chronological Reading. " .
            "Gap = " . abs($coveredChapters - $requiredChapters) . " chapters.");
    }
}
