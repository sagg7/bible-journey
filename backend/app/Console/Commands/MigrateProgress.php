<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra el progreso real de usuarios de un plan a otro.
 *
 * - user_canonical_progress: los block_id son globales (viven en el CRS, no en
 *   el plan), así que se copian tal cual con el nuevo plan_id, siempre que el
 *   CRS del bloque exista como nodo en el plan destino.
 * - user_event_progress: los node_id son por-plan; se remapean vía crs_id
 *   (nodo viejo → CRS → nodo nuevo del plan destino).
 *
 * Idempotente: usa insertOrIgnore contra las claves únicas
 * (user_id, block_id|node_id, plan_id) — re-ejecutar no duplica ni sobreescribe
 * progreso ya registrado en el plan destino.
 */
class MigrateProgress extends Command
{
    protected $signature = 'stream-plans:migrate-progress
                            {from_plan_id : Plan de origen}
                            {to_plan_id   : Plan de destino}
                            {--dry-run    : Solo reportar, sin escribir}';

    protected $description = 'Copia el progreso de usuarios (bloques y nodos) de un plan a otro';

    public function handle(): int
    {
        $fromId = (int) $this->argument('from_plan_id');
        $toId   = (int) $this->argument('to_plan_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($fromId === $toId) {
            $this->error('from_plan_id y to_plan_id no pueden ser el mismo plan.');
            return self::FAILURE;
        }

        foreach ([$fromId, $toId] as $id) {
            if (! DB::table('stream_plans')->where('id', $id)->exists()) {
                $this->error("Plan #{$id} no existe.");
                return self::FAILURE;
            }
        }

        // ── Bloques ──────────────────────────────────────────────────────────
        $canonicalRows = DB::table('user_canonical_progress as ucp')
            ->join('reading_blocks as rb', 'rb.id', '=', 'ucp.block_id')
            ->join('stream_plan_nodes as spn', function ($j) use ($toId) {
                $j->on('spn.crs_id', '=', 'rb.crs_id')->where('spn.plan_id', '=', $toId);
            })
            ->where('ucp.plan_id', $fromId)
            ->select('ucp.user_id', 'ucp.block_id', 'ucp.status', 'ucp.started_at', 'ucp.completed_at')
            ->get();

        $canonicalUnmapped = DB::table('user_canonical_progress as ucp')
            ->join('reading_blocks as rb', 'rb.id', '=', 'ucp.block_id')
            ->where('ucp.plan_id', $fromId)
            ->whereNotExists(function ($q) use ($toId) {
                $q->select(DB::raw(1))->from('stream_plan_nodes as spn')
                    ->whereColumn('spn.crs_id', 'rb.crs_id')
                    ->where('spn.plan_id', $toId);
            })
            ->count();

        // ── Nodos (remapeo vía CRS) ──────────────────────────────────────────
        $eventRows = DB::table('user_event_progress as uep')
            ->join('stream_plan_nodes as old_n', 'old_n.id', '=', 'uep.node_id')
            ->join('stream_plan_nodes as new_n', function ($j) use ($toId) {
                $j->on('new_n.crs_id', '=', 'old_n.crs_id')->where('new_n.plan_id', '=', $toId);
            })
            ->where('uep.plan_id', $fromId)
            ->select(
                'uep.user_id',
                'new_n.id as new_node_id',
                'uep.state',
                'uep.pending_block_count',
                'uep.started_at',
                'uep.primary_completed_at',
                'uep.completed_at'
            )
            ->get();

        $eventUnmapped = DB::table('user_event_progress as uep')
            ->join('stream_plan_nodes as old_n', 'old_n.id', '=', 'uep.node_id')
            ->where('uep.plan_id', $fromId)
            ->whereNotExists(function ($q) use ($toId) {
                $q->select(DB::raw(1))->from('stream_plan_nodes as new_n')
                    ->whereColumn('new_n.crs_id', 'old_n.crs_id')
                    ->where('new_n.plan_id', $toId);
            })
            ->count();

        $this->info("Progreso a migrar Plan #{$fromId} → #{$toId}:");
        $this->line("  Bloques: {$canonicalRows->count()} mapeados, {$canonicalUnmapped} sin mapeo");
        $this->line("  Nodos:   {$eventRows->count()} mapeados, {$eventUnmapped} sin mapeo");

        if ($dryRun) {
            $this->warn('DRY RUN — nada escrito.');
            return ($canonicalUnmapped + $eventUnmapped) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $now = now();

        DB::transaction(function () use ($canonicalRows, $eventRows, $toId, $now) {
            foreach ($canonicalRows->chunk(500) as $chunk) {
                DB::table('user_canonical_progress')->insertOrIgnore(
                    $chunk->map(fn ($r) => [
                        'user_id'      => $r->user_id,
                        'block_id'     => $r->block_id,
                        'plan_id'      => $toId,
                        'status'       => $r->status,
                        'started_at'   => $r->started_at,
                        'completed_at' => $r->completed_at,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ])->all()
                );
            }

            foreach ($eventRows->chunk(500) as $chunk) {
                DB::table('user_event_progress')->insertOrIgnore(
                    $chunk->map(fn ($r) => [
                        'user_id'              => $r->user_id,
                        'node_id'              => $r->new_node_id,
                        'plan_id'              => $toId,
                        'state'                => $r->state,
                        'pending_block_count'  => $r->pending_block_count,
                        'started_at'           => $r->started_at,
                        'primary_completed_at' => $r->primary_completed_at,
                        'completed_at'         => $r->completed_at,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ])->all()
                );
            }
        });

        $this->info('  ✓ Migración completada (insertOrIgnore: el progreso existente en el destino se respeta).');

        if (($canonicalUnmapped + $eventUnmapped) > 0) {
            $this->warn('  ⚠ Hay registros sin mapeo (CRS ausente en el plan destino). Revisar antes de archivar el plan origen.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
