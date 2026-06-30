<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CompareGroup;
use Illuminate\Http\JsonResponse;

class CompareGroupController extends Controller
{
    // GET /api/v2/compare-groups/{id}
    public function show(string $id): JsonResponse
    {
        $group = CompareGroup::with([
            'crs:id,source_map,title_es',
            'links.sourceBlock:id,source_map,role,display_reference,display_label_es,placement_confidence',
            'links.targetBlock:id,source_map,role,display_reference,display_label_es,placement_confidence',
        ])->findOrFail($id);

        // Collect all distinct blocks involved in this compare group
        $blockIds = collect();
        foreach ($group->links as $link) {
            if ($link->sourceBlock) $blockIds->push($link->sourceBlock->id);
            if ($link->targetBlock) $blockIds->push($link->targetBlock->id);
        }
        $blockIds = $blockIds->unique()->values();

        // Build the relatos (accounts) — one per unique block
        $blockMap = [];
        foreach ($group->links as $link) {
            foreach ([$link->sourceBlock, $link->targetBlock] as $block) {
                if (! $block || isset($blockMap[$block->id])) continue;
                $blockMap[$block->id] = [
                    'id'               => $block->id,
                    'source_map'       => $block->source_map,
                    'role'             => $block->role,
                    'display_reference'=> $block->display_reference,
                    'display_label_es' => $block->display_label_es,
                    'confidence'       => $block->placement_confidence,
                    'has_text'         => $block->passage_id !== null,
                ];
            }
        }

        return response()->json([
            'id'                   => $group->id,
            'title_es'             => $group->title_es,
            'editorial_summary_es' => $group->editorial_summary_es,
            'disclaimer_es'        => $group->disclaimer_es,
            'relation_level'       => $group->relation_level,
            'key_differences_es'   => $group->key_differences_es ?? [],
            'crs' => $group->crs ? [
                'id'         => $group->crs->id,
                'source_map' => $group->crs->source_map,
                'title_es'   => $group->crs->title_es,
            ] : null,
            'accounts' => array_values($blockMap),
            'links' => $group->links->map(fn($l) => [
                'id'             => $l->id,
                'relation_type'  => $l->relation_type,
                'confidence'     => $l->confidence,
                'evidence_note'  => $l->evidence_note,
                'source_block_id'=> $l->source_block_id,
                'target_block_id'=> $l->target_block_id,
            ]),
        ]);
    }
}
