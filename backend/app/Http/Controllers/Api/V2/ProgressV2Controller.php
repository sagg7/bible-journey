<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ReadingBlock;
use App\Models\StreamPlan;
use App\Models\StreamPlanNode;
use App\Models\UserCanonicalProgress;
use App\Models\UserEventProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressV2Controller extends Controller
{
    // POST /api/v2/progress/blocks/{blockId}
    // Body: { "status": "completed", "plan_id": 1 }
    public function markBlock(Request $request, string $blockId): JsonResponse
    {
        $request->validate([
            'status'  => 'required|in:in_progress,completed,deferred,skipped',
            'plan_id' => 'required|integer|exists:stream_plans,id',
        ]);

        $block  = ReadingBlock::findOrFail($blockId);
        $planId = $request->integer('plan_id');
        $status = $request->string('status');
        $user   = $request->user();

        DB::transaction(function () use ($user, $block, $planId, $status) {
            $existing = UserCanonicalProgress::where('user_id', $user->id)
                ->where('block_id', $block->id)
                ->where('plan_id', $planId)
                ->first();

            $progress = UserCanonicalProgress::updateOrCreate(
                ['user_id' => $user->id, 'block_id' => $block->id, 'plan_id' => $planId],
                [
                    'status'       => $status,
                    'started_at'   => $existing->started_at ?? now(),
                    'completed_at' => $status === 'completed' ? now() : null,
                ]
            );

            // Re-evaluate parent node state
            $node = StreamPlanNode::where('plan_id', $planId)
                ->where('crs_id', $block->crs_id)
                ->first();

            if ($node) {
                $this->refreshNodeState($user->id, $node, $planId);
            }
        });

        return response()->json(['ok' => true, 'status' => $status]);
    }

    // GET /api/v2/progress/summary
    public function summary(Request $request): JsonResponse
    {
        $user   = $request->user();
        $planId = $request->query('plan_id')
            ?? StreamPlan::latestPublished()->id
            ?? null;

        if (! $planId) {
            return response()->json(['error' => 'No active plan'], 404);
        }

        $canonicalRows = UserCanonicalProgress::where('user_id', $user->id)
            ->where('plan_id', $planId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $narrativeRows = UserEventProgress::where('user_id', $user->id)
            ->where('plan_id', $planId)
            ->selectRaw('state, COUNT(*) as cnt')
            ->groupBy('state')
            ->pluck('cnt', 'state');

        $totalBlocks = ReadingBlock::whereHas(
            'crs',
            fn($q) => $q->where('review_status', '!=', 'blocked')
        )->count();

        $totalNodes = StreamPlanNode::where('plan_id', $planId)->count();

        return response()->json([
            'plan_id'   => (int) $planId,
            'canonical' => [
                'total'     => $totalBlocks,
                'completed' => (int) ($canonicalRows['completed'] ?? 0),
                'in_progress' => (int) ($canonicalRows['in_progress'] ?? 0),
                'deferred'  => (int) ($canonicalRows['deferred'] ?? 0),
                'percent'   => $totalBlocks > 0
                    ? round(($canonicalRows['completed'] ?? 0) / $totalBlocks * 100, 1)
                    : 0,
            ],
            'narrative' => [
                'total'            => $totalNodes,
                'not_started'      => (int) ($narrativeRows['not_started'] ?? 0),
                'in_progress'      => (int) ($narrativeRows['in_progress'] ?? 0),
                'primary_complete' => (int) ($narrativeRows['primary_complete'] ?? 0),
                'fully_complete'   => (int) ($narrativeRows['fully_complete'] ?? 0),
                'percent'          => $totalNodes > 0
                    ? round(
                        (($narrativeRows['fully_complete'] ?? 0) + ($narrativeRows['primary_complete'] ?? 0))
                        / $totalNodes * 100, 1
                      )
                    : 0,
            ],
        ]);
    }

    // POST /api/v2/progress/nodes/{nodeId}
    // Body: { "state": "narrative_complete|deferred", "plan_id": 1 }
    // Used by "Continuar la historia" — defers all non-anchor blocks
    public function markNodeState(Request $request, string $nodeId): JsonResponse
    {
        $request->validate([
            'state'   => 'required|in:narrative_complete,deferred,in_progress',
            'plan_id' => 'required|integer|exists:stream_plans,id',
        ]);

        $user   = $request->user();
        $planId = $request->integer('plan_id');
        $state  = $request->string('state');

        $node = StreamPlanNode::where('id', $nodeId)
            ->where('plan_id', $planId)
            ->firstOrFail();

        DB::transaction(function () use ($user, $node, $planId, $state) {
            // If narrative_complete: mark all non-anchor blocks as deferred
            if ($state === 'narrative_complete') {
                $crs           = $node->crs()->with('blocks')->first();
                $relatedBlocks = $crs->blocks->where('role', '!=', 'narrative_anchor');

                foreach ($relatedBlocks as $block) {
                    $existingBlock = UserCanonicalProgress::where('user_id', $user->id)
                        ->where('block_id', $block->id)
                        ->where('plan_id', $planId)
                        ->first();

                    UserCanonicalProgress::updateOrCreate(
                        ['user_id' => $user->id, 'block_id' => $block->id, 'plan_id' => $planId],
                        ['status' => 'deferred', 'started_at' => $existingBlock->started_at ?? now()]
                    );
                }
            }

            $existingEvent = UserEventProgress::where('user_id', $user->id)
                ->where('node_id', $node->id)
                ->where('plan_id', $planId)
                ->first();

            UserEventProgress::updateOrCreate(
                ['user_id' => $user->id, 'node_id' => $node->id, 'plan_id' => $planId],
                [
                    'state'                => $state,
                    'started_at'           => $existingEvent->started_at ?? now(),
                    // Solo narrative_complete implica que el ancla fue leída;
                    // deferred/in_progress no deben sellar la marca de tiempo.
                    'primary_completed_at' => $state === 'narrative_complete'
                        ? ($existingEvent->primary_completed_at ?? now())
                        : $existingEvent?->primary_completed_at,
                ]
            );
        });

        return response()->json(['ok' => true, 'state' => $state]);
    }

    private function refreshNodeState(int $userId, StreamPlanNode $node, int $planId): void
    {
        $crs    = $node->crs;
        $blocks = $crs->blocks;

        $anchorBlock   = $blocks->firstWhere('role', 'narrative_anchor');
        $relatedBlocks = $blocks->where('role', '!=', 'narrative_anchor');

        $completedIds = UserCanonicalProgress::where('user_id', $userId)
            ->where('plan_id', $planId)
            ->whereIn('block_id', $blocks->pluck('id'))
            ->where('status', 'completed')
            ->pluck('block_id')
            ->toArray();

        $anchorDone   = $anchorBlock && in_array($anchorBlock->id, $completedIds);
        $allDone      = $blocks->every(fn($b) => in_array($b->id, $completedIds));
        $pendingCount = $relatedBlocks->filter(fn($b) => ! in_array($b->id, $completedIds))->count();

        $state = 'not_started';
        if ($allDone) {
            $state = 'fully_complete';
        } elseif ($anchorDone) {
            $state = 'primary_complete';
        } elseif (! empty($completedIds)) {
            $state = 'in_progress';
        }

        $existingEvent = UserEventProgress::where('user_id', $userId)
            ->where('node_id', $node->id)
            ->where('plan_id', $planId)
            ->first();

        UserEventProgress::updateOrCreate(
            ['user_id' => $userId, 'node_id' => $node->id, 'plan_id' => $planId],
            [
                'state'                => $state,
                'pending_block_count'  => $pendingCount,
                'started_at'           => $existingEvent->started_at ?? now(),
                'primary_completed_at' => $anchorDone ? ($existingEvent->primary_completed_at ?? now()) : null,
                'completed_at'         => $allDone ? ($existingEvent->completed_at ?? now()) : null,
            ]
        );
    }
}
