<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanPublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea dos planes (uno published, otro draft) que comparten el mismo CRS,
     * con un bloque y progreso de usuario en el plan viejo.
     *
     * @return array{userId:int, oldPlanId:int, newPlanId:int, blockId:int, oldNodeId:int, newNodeId:int}
     */
    private function fixture(): array
    {
        $now = now();

        $oldPlanId = DB::table('stream_plans')->insertGetId([
            'profile_id' => 'cautious_default', 'locale' => 'es',
            'publication_status' => 'published', 'published_at' => $now->copy()->subDay(),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $newPlanId = DB::table('stream_plans')->insertGetId([
            'profile_id' => 'cautious_default', 'locale' => 'es',
            'publication_status' => 'draft',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $crsId = DB::table('chronological_reading_sets')->insertGetId([
            'source_map' => 'CRS-TST-001', 'era' => 'Test', 'era_slug' => 'test',
            'title_es' => 'CRS de prueba', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $blockId = DB::table('reading_blocks')->insertGetId([
            'crs_id' => $crsId, 'source_map' => 'BLK-TST-001', 'book' => 'Genesis',
            'passage_start' => '1', 'passage_end' => '1', 'display_reference' => 'Génesis 1',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $oldNodeId = DB::table('stream_plan_nodes')->insertGetId([
            'plan_id' => $oldPlanId, 'crs_id' => $crsId, 'rank' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $newNodeId = DB::table('stream_plan_nodes')->insertGetId([
            'plan_id' => $newPlanId, 'crs_id' => $crsId, 'rank' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $user = User::factory()->create();

        DB::table('user_canonical_progress')->insert([
            'user_id' => $user->id, 'block_id' => $blockId, 'plan_id' => $oldPlanId,
            'status' => 'completed', 'started_at' => $now, 'completed_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('user_event_progress')->insert([
            'user_id' => $user->id, 'node_id' => $oldNodeId, 'plan_id' => $oldPlanId,
            'state' => 'fully_complete', 'pending_block_count' => 0,
            'started_at' => $now, 'completed_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return [
            'userId' => $user->id, 'oldPlanId' => $oldPlanId, 'newPlanId' => $newPlanId,
            'blockId' => $blockId, 'oldNodeId' => $oldNodeId, 'newNodeId' => $newNodeId,
        ];
    }

    public function test_publish_requires_confirm_flag(): void
    {
        $f = $this->fixture();

        $exit = Artisan::call('stream-plans:publish', ['plan_id' => $f['newPlanId']]);

        $this->assertSame(1, $exit);
        $this->assertDatabaseHas('stream_plans', [
            'id' => $f['newPlanId'], 'publication_status' => 'draft',
        ]);
    }

    public function test_migrate_progress_copies_blocks_and_remaps_nodes(): void
    {
        $f = $this->fixture();

        $exit = Artisan::call('stream-plans:migrate-progress', [
            'from_plan_id' => $f['oldPlanId'], 'to_plan_id' => $f['newPlanId'],
        ]);

        $this->assertSame(0, $exit);

        // Bloque copiado con el nuevo plan_id (block_id global se conserva)
        $this->assertDatabaseHas('user_canonical_progress', [
            'user_id' => $f['userId'], 'block_id' => $f['blockId'],
            'plan_id' => $f['newPlanId'], 'status' => 'completed',
        ]);

        // Nodo remapeado vía CRS al nodo del plan nuevo
        $this->assertDatabaseHas('user_event_progress', [
            'user_id' => $f['userId'], 'node_id' => $f['newNodeId'],
            'plan_id' => $f['newPlanId'], 'state' => 'fully_complete',
        ]);

        // El progreso original no se toca
        $this->assertDatabaseHas('user_canonical_progress', [
            'user_id' => $f['userId'], 'plan_id' => $f['oldPlanId'], 'status' => 'completed',
        ]);
    }

    public function test_migrate_progress_is_idempotent(): void
    {
        $f = $this->fixture();

        Artisan::call('stream-plans:migrate-progress', [
            'from_plan_id' => $f['oldPlanId'], 'to_plan_id' => $f['newPlanId'],
        ]);
        Artisan::call('stream-plans:migrate-progress', [
            'from_plan_id' => $f['oldPlanId'], 'to_plan_id' => $f['newPlanId'],
        ]);

        $this->assertSame(1, DB::table('user_canonical_progress')
            ->where('plan_id', $f['newPlanId'])->count());
        $this->assertSame(1, DB::table('user_event_progress')
            ->where('plan_id', $f['newPlanId'])->count());
    }

    public function test_rollback_restores_previous_plan_and_demotes_current(): void
    {
        $f = $this->fixture();

        // Simular que el nuevo quedó publicado y el viejo archivado
        DB::table('stream_plans')->where('id', $f['newPlanId'])
            ->update(['publication_status' => 'published', 'published_at' => now()]);
        DB::table('stream_plans')->where('id', $f['oldPlanId'])
            ->update(['publication_status' => 'archived']);

        $exit = Artisan::call('stream-plans:rollback', [
            'plan_id' => $f['oldPlanId'], '--confirm' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('stream_plans', [
            'id' => $f['oldPlanId'], 'publication_status' => 'published',
        ]);
        $this->assertDatabaseHas('stream_plans', [
            'id' => $f['newPlanId'], 'publication_status' => 'draft',
        ]);
        // Nunca dos planes publicados a la vez
        $this->assertSame(1, DB::table('stream_plans')
            ->where('publication_status', 'published')->count());
    }

    public function test_mutation_commands_refuse_published_plans(): void
    {
        $f = $this->fixture();

        $exitNormalize = Artisan::call('stream-plans:normalize-sequence', [
            'planId' => $f['oldPlanId'],
        ]);
        $exitPsalm = Artisan::call('stream-plans:fix-psalm-chronology', [
            'planId' => $f['oldPlanId'],
        ]);

        $this->assertSame(1, $exitNormalize, 'normalize-sequence debe rechazar un plan publicado');
        $this->assertSame(1, $exitPsalm, 'fix-psalm-chronology debe rechazar un plan publicado');

        // El rank no cambió
        $this->assertDatabaseHas('stream_plan_nodes', [
            'id' => $f['oldNodeId'], 'rank' => 1,
        ]);
    }

    public function test_mutation_commands_allow_draft_plans(): void
    {
        $f = $this->fixture();

        $exit = Artisan::call('stream-plans:normalize-sequence', [
            'planId' => $f['newPlanId'],
        ]);

        $this->assertSame(0, $exit, 'normalize-sequence debe aceptar un plan draft');
    }
}
