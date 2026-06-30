<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ChronologicalReadingSet;
use App\Models\EvidenceRecord;
use App\Models\StreamPlanNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExplanationController extends Controller
{
    // GET /api/v2/explanations/{crsId}
    // Answers: "¿Por qué está aquí este pasaje?"
    public function show(Request $request, string $crsId): JsonResponse
    {
        $crs = ChronologicalReadingSet::with('blocks')->findOrFail($crsId);

        // Find active node for plan context
        $planId = $request->query('plan_id');
        $node   = $planId
            ? StreamPlanNode::where('plan_id', $planId)->where('crs_id', $crs->id)->first()
            : StreamPlanNode::where('crs_id', $crs->id)->latest('id')->first();

        // Load evidence records keyed in source_keys of the blocks
        $sourceKeys = $crs->blocks
            ->pluck('source_keys')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $evidenceRecords = EvidenceRecord::whereIn('source_key', $sourceKeys)
            ->get()
            ->map(fn($e) => [
                'source_key'    => $e->source_key,
                'claim'         => $e->claim,
                'evidence_type' => $e->evidence_type,
                'confidence'    => $e->confidence,
                'source_ref'    => $e->source_reference,
            ]);

        // Build placement rationale from CRS fields
        $rationale = $this->buildPlacementRationale($crs);

        return response()->json([
            'crs_id'        => $crs->id,
            'source_map'    => $crs->source_map,
            'title_es'      => $crs->title_es,
            'era'           => $crs->era,
            'placement' => [
                'confidence'     => $crs->placement_confidence,
                'event_confidence' => $crs->event_confidence,
                'relation_confidence' => $crs->relation_confidence,
                'rationale_es'   => $rationale,
                'editorial_note' => $crs->editorial_note,
            ],
            'node' => $node ? [
                'id'           => $node->id,
                'rank'         => $node->rank,
                'display_mode' => $node->display_mode,
                'explanation_es' => $node->explanation_es,
            ] : null,
            'evidence'      => $evidenceRecords,
            'blocks'        => $crs->blocks->map(fn($b) => [
                'id'               => $b->id,
                'role'             => $b->role,
                'display_reference'=> $b->display_reference,
                'placement_confidence' => $b->placement_confidence,
                'display_label_es' => $b->display_label_es,
            ]),
        ]);
    }

    private function buildPlacementRationale(ChronologicalReadingSet $crs): string
    {
        $conf  = $crs->placement_confidence;
        $era   = $crs->era;

        $labels = [
            'alta'              => 'alta certeza',
            'probable'          => 'probable',
            'debatida'          => 'debatida entre especialistas',
            'tradicion_popular' => 'tradición popular sin consenso académico',
            'especulativa'      => 'especulativa',
        ];

        $label = $labels[$conf] ?? $conf;
        $base  = "La ubicación de este pasaje en la era «{$era}» es de certeza {$label}.";

        if ($crs->narrative_flow_message_es) {
            $base .= ' ' . $crs->narrative_flow_message_es;
        }

        if ($crs->editorial_note) {
            $base .= ' Nota editorial: ' . $crs->editorial_note;
        }

        return $base;
    }
}
