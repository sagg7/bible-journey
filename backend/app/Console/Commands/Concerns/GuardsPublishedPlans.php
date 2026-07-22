<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Guard de inmutabilidad para planes publicados.
 *
 * Un plan `published` es el artefacto que ven los usuarios: mutarlo in-place
 * rompe la garantía "un plan publicado no puede modificarse silenciosamente"
 * (así se corrompió el Plan 10 el 2026-07-05). Los comandos que reordenan o
 * modifican nodos deben llamar assertPlanIsMutable() antes de escribir.
 */
trait GuardsPublishedPlans
{
    /**
     * @return bool true si se puede continuar; false si el comando debe abortar.
     */
    protected function assertPlanIsMutable(int $planId, bool $forcePublished = false): bool
    {
        $plan = DB::table('stream_plans')->where('id', $planId)->first();

        if (! $plan) {
            $this->error("Plan #{$planId} not found.");
            return false;
        }

        if ($plan->publication_status !== 'published') {
            return true;
        }

        if (! $forcePublished) {
            $this->error("Plan #{$planId} está PUBLICADO. Los planes publicados son inmutables.");
            $this->line('Opciones:');
            $this->line('  1. Clonar y recompilar: php artisan harmonize:compile (recomendado)');
            $this->line('  2. Forzar con respaldo:  añadir --force-published (guarda snapshot de nodos antes de escribir)');
            return false;
        }

        // Force: snapshot completo de los nodos antes de permitir la mutación,
        // para que la modificación nunca sea silenciosa ni irrecuperable.
        $nodes = DB::table('stream_plan_nodes')
            ->where('plan_id', $planId)
            ->orderBy('rank')
            ->get();

        $path = sprintf(
            'reports/plan-%d-node-snapshot-%s.json',
            $planId,
            now()->format('Ymd-His')
        );

        Storage::makeDirectory('reports');
        Storage::put($path, json_encode([
            'plan_id'     => $planId,
            'reason'      => 'pre-mutation snapshot (--force-published)',
            'command'     => $this->getName(),
            'executed_by' => get_current_user() ?: 'cli',
            'timestamp'   => now()->toIso8601String(),
            'nodes'       => $nodes,
        ], JSON_PRETTY_PRINT));

        $this->warn("⚠ Plan #{$planId} está publicado; procediendo por --force-published.");
        $this->warn('  Snapshot de nodos guardado en storage/app/' . $path);

        return true;
    }
}
