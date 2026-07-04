<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\StreamPlan;
use App\Models\StreamPlanNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamPlanController extends Controller
{
    // GET /api/v2/stream-plans/active  →  latest published plan for profile + locale
    // GET /api/v2/stream-plans/{id}    →  specific plan
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user('sanctum');
        $includeTestOnly = (bool) ($user?->has_test_access);
        $hasAccess = (bool) ($user?->hasPremiumAccess());

        $plan = $id === 'active'
            ? StreamPlan::latestPublished(
                $request->query('profile', 'cautious_default'),
                $request->query('locale', 'es'),
                $includeTestOnly
              )
            : StreamPlan::findOrFail($id);

        if (! $plan || ($plan->is_test_only && ! $includeTestOnly)) {
            return response()->json(['error' => 'No published plan found'], 404);
        }

        $nodes = $plan->nodes()
            ->with([
                'crs:id,source_map,era,era_slug,sort_key,title_es,placement_confidence,review_status,is_premium',
                'crs.blocks' => fn($q) => $q->orderBy('display_order')
                    ->select('id', 'crs_id', 'display_reference', 'display_order'),
            ])
            ->orderBy('rank')
            ->get()
            ->map(fn($node) => $this->formatNode($node, $hasAccess));

        $edges = $plan->edges()
            ->select('id', 'from_node_id', 'to_node_id', 'edge_type', 'score', 'priority')
            ->get();

        return response()->json([
            'id'                  => $plan->id,
            'profile_id'          => $plan->profile_id,
            'locale'              => $plan->locale,
            'publication_status'  => $plan->publication_status,
            'node_count'          => $plan->node_count,
            'edge_count'          => $plan->edge_count,
            'published_at'        => $plan->published_at?->toIsoString(),
            'validation_hash'     => $plan->validation_hash,
            'nodes'               => $nodes,
            'edges'               => $edges,
        ]);
    }

    // GET /api/v2/stream-plans/{id}/nodes/{nodeId}
    public function showNode(Request $request, string $planId, string $nodeId): JsonResponse
    {
        $user = $request->user('sanctum');
        $includeTestOnly = (bool) ($user?->has_test_access);
        $hasAccess = (bool) ($user?->hasPremiumAccess());

        $plan = $planId === 'active'
            ? StreamPlan::latestPublished(
                $request->query('profile', 'cautious_default'),
                $request->query('locale', 'es'),
                $includeTestOnly
              )
            : StreamPlan::findOrFail($planId);

        if (! $plan || ($plan->is_test_only && ! $includeTestOnly)) {
            return response()->json(['error' => 'No published plan found'], 404);
        }

        $node = StreamPlanNode::where('plan_id', $plan->id)
            ->where('id', $nodeId)
            ->with([
                'crs:id,source_map,era,era_slug,sort_key,title_es,title_en,placement_confidence,event_confidence,narrative_flow_message_es,editorial_note,is_premium',
                'crs.blocks',
                'crs.studyContent',
                'crs.spiritOfProphecyContents',
                'compareGroup',
            ])
            ->firstOrFail();

        $crs = $node->crs;

        if ($crs->is_premium && ! $hasAccess) {
            return response()->json([
                'node_id'   => $node->id,
                'rank'      => $node->rank,
                'locked'    => true,
                'crs' => [
                    'id'       => $crs->id,
                    'era'      => $crs->era,
                    'era_slug' => $crs->era_slug,
                    'title_es' => $crs->title_es,
                    'title_en' => $crs->title_en,
                ],
            ]);
        }

        $blocks = $crs->blocks->map(fn($b) => [
            'id'                        => $b->id,
            'source_map'                => $b->source_map,
            'role'                      => $b->role,
            'display_order'             => $b->display_order,
            'display_reference'         => $b->display_reference,
            'display_label_es'          => $b->display_label_es,
            'placement_confidence'      => $b->placement_confidence,
            'required_in_complete_mode' => $b->required_in_complete_mode,
            'shown_in_narrative_flow'   => $b->shown_in_narrative_flow,
            'has_text'                  => $b->passage_id !== null || $b->start_book_id !== null,
        ]);

        $outEdges = $node->outboundEdges()
            ->select('to_node_id', 'edge_type', 'score')
            ->get();

        return response()->json([
            'node_id'            => $node->id,
            'rank'               => $node->rank,
            'display_mode'       => $node->display_mode,
            'required_state'     => $node->required_state,
            'explanation_es'     => $node->explanation_es,
            'locked'             => false,
            'crs' => [
                'id'                     => $crs->id,
                'source_map'             => $crs->source_map,
                'era'                    => $crs->era,
                'era_slug'               => $crs->era_slug,
                'title_es'               => $crs->title_es,
                'title_en'               => $crs->title_en,
                'placement_confidence'   => $crs->placement_confidence,
                'event_confidence'       => $crs->event_confidence,
                'narrative_flow_message' => $crs->narrative_flow_message_es,
                'editorial_note'         => $crs->editorial_note,
            ],
            'blocks'             => $blocks,
            'compare_group'      => $node->compareGroup ? [
                'id'                   => $node->compareGroup->id,
                'title_es'             => $node->compareGroup->title_es,
                'relation_level'       => $node->compareGroup->relation_level,
            ] : null,
            'study_content'      => $this->formatStudyContent($crs),
            'spirit_of_prophecy' => $this->formatSpiritOfProphecy($crs, $request->query('locale', 'es')),
            'outbound_edges'     => $outEdges,
        ]);
    }

    // GET /api/v2/stream-plans/{id}/chronological
    // Returns only main-stream nodes grouped by user_facing_era, ordered by rank.
    public function chronological(Request $request, string $id): JsonResponse
    {
        $user = $request->user('sanctum');
        $includeTestOnly = (bool) ($user?->has_test_access);
        $hasAccess = (bool) ($user?->hasPremiumAccess());

        $plan = $id === 'active'
            ? StreamPlan::latestPublished(
                $request->query('profile', 'cautious_default'),
                $request->query('locale', 'es'),
                $includeTestOnly
              )
            : StreamPlan::findOrFail($id);

        if (! $plan || ($plan->is_test_only && ! $includeTestOnly)) {
            return response()->json(['error' => 'No published plan found'], 404);
        }

        $mainNodes = $plan->nodes()
            ->where('is_main_stream_node', true)
            ->with([
                'crs:id,source_map,era,era_slug,sort_key,title_es,placement_confidence,review_status,stream_role,user_facing_era,user_facing_era_sort,is_main_stream_node,is_premium',
                'crs.blocks' => fn($q) => $q->orderBy('display_order')
                    ->select('id', 'crs_id', 'display_reference', 'display_order'),
            ])
            ->reorder()
            ->orderBy('user_facing_era_sort')
            ->orderBy('rank')
            ->get();

        // Group by user_facing_era preserving order
        $eraOrder  = [];
        $eraGroups = [];
        foreach ($mainNodes as $node) {
            $eraKey = $node->user_facing_era ?? 'General';
            if (! isset($eraGroups[$eraKey])) {
                $eraOrder[]          = $eraKey;
                $eraGroups[$eraKey]  = [
                    'title'               => $eraKey,
                    'user_facing_era_sort'=> $node->user_facing_era_sort,
                    'nodes'               => [],
                ];
            }
            $eraGroups[$eraKey]['nodes'][] = $this->formatNode($node, $hasAccess);
        }

        $eras = array_values(array_map(fn($k) => $eraGroups[$k], $eraOrder));

        return response()->json([
            'plan_id'     => $plan->id,
            'profile_id'  => $plan->profile_id,
            'node_count'  => $mainNodes->count(),
            'eras'        => $eras,
        ]);
    }

    private function formatNode(StreamPlanNode $node, bool $hasAccess = false): array
    {
        return [
            'id'                   => $node->id,
            'rank'                 => $node->rank,
            'display_mode'         => $node->display_mode,
            'crs_id'               => $node->crs_id,
            'source_map'           => $node->crs?->source_map,
            'title_es'             => $node->crs?->title_es,
            'reference'            => $node->crs?->blocks?->first()?->display_reference,
            'era'                  => $node->crs?->era,
            'era_slug'             => $node->crs?->era_slug,
            'sort_key'             => $node->crs?->sort_key,
            'confidence'           => $node->crs?->placement_confidence,
            'stream_role'          => $node->stream_role,
            'user_facing_era'      => $node->user_facing_era,
            'user_facing_era_sort' => $node->user_facing_era_sort,
            'is_main_stream_node'  => $node->is_main_stream_node,
            'locked'               => (bool) ($node->crs?->is_premium) && ! $hasAccess,
        ];
    }

    private function formatStudyContent($crs): array
    {
        $content = $crs->studyContent;

        return [
            'summary_es'  => $content?->summary_es,
            'context_es'  => $content?->context_es,
            'people'      => $content?->people ?? [],
            'places'      => $content?->places ?? [],
            'connections' => $content?->connections ?? [],
            'sources'     => $content?->sources ?? [],
            'version'     => $content?->content_version,
        ];
    }

    private function formatSpiritOfProphecy($crs, string $locale): array
    {
        $content = $crs->spiritOfProphecyContents->firstWhere('locale', $locale)
            ?? $crs->spiritOfProphecyContents->first();

        return [
            'locale'            => $content?->locale,
            'source_book_code'  => $content?->source_book_code,
            'source_book_title' => $content?->source_book_title,
            'excerpts'          => $content?->excerpts ?? [],
            'copyright'         => '© Ellen G. White Estate',
            'version'           => $content?->content_version,
        ];
    }
}
