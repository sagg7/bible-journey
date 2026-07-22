<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function bookFixture(): int
    {
        return DB::table('biblical_books')->insertGetId([
            'osis_code' => 'GEN', 'slug' => 'genesis', 'name_es' => 'Génesis',
            'name_en' => 'Genesis', 'testament' => 'AT', 'canonical_order' => 1,
            'chapter_count' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function highlightFor(User $user, int $bookId): int
    {
        $colorId = DB::table('highlight_colors')->insertGetId([
            'user_id' => $user->id, 'color_hex' => '#FFEB3B',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('verse_highlights')->insertGetId([
            'user_id' => $user->id, 'book_id' => $bookId, 'chapter_number' => 1,
            'verse_start' => 1, 'verse_end' => 3, 'highlight_color_id' => $colorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── IDOR / acceso cruzado ────────────────────────────────────────────────

    public function test_user_cannot_read_or_delete_another_users_highlights(): void
    {
        $bookId = $this->bookFixture();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $aliceHighlight = $this->highlightFor($alice, $bookId);

        // Bob no ve los highlights de Alice
        $this->actingAs($bob, 'sanctum')
            ->getJson('/api/v2/highlights')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Bob no puede borrar el highlight de Alice (404, no 403 — no filtra existencia)
        $this->actingAs($bob, 'sanctum')
            ->deleteJson("/api/v2/highlights/{$aliceHighlight}")
            ->assertNotFound();

        $this->assertDatabaseHas('verse_highlights', ['id' => $aliceHighlight]);
    }

    public function test_progress_summary_is_scoped_to_the_authenticated_user(): void
    {
        $now = now();
        $planId = DB::table('stream_plans')->insertGetId([
            'profile_id' => 'cautious_default', 'locale' => 'es',
            'publication_status' => 'published', 'published_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $crsId = DB::table('chronological_reading_sets')->insertGetId([
            'source_map' => 'CRS-SEC-001', 'era' => 'Test', 'era_slug' => 'test',
            'title_es' => 'CRS', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $blockId = DB::table('reading_blocks')->insertGetId([
            'crs_id' => $crsId, 'source_map' => 'BLK-SEC-001', 'book' => 'Genesis',
            'passage_start' => '1', 'passage_end' => '1', 'display_reference' => 'Génesis 1',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('stream_plan_nodes')->insert([
            'plan_id' => $planId, 'crs_id' => $crsId, 'rank' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        DB::table('user_canonical_progress')->insert([
            'user_id' => $alice->id, 'block_id' => $blockId, 'plan_id' => $planId,
            'status' => 'completed', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($bob, 'sanctum')
            ->getJson("/api/v2/progress/summary?plan_id={$planId}")
            ->assertOk()
            ->assertJsonPath('canonical.completed', 0);

        $this->actingAs($alice, 'sanctum')
            ->getJson("/api/v2/progress/summary?plan_id={$planId}")
            ->assertOk()
            ->assertJsonPath('canonical.completed', 1);
    }

    // ── Eliminación de cuenta ────────────────────────────────────────────────

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correcta123')]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/me', ['password' => 'incorrecta'])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_cascades_personal_data(): void
    {
        $bookId = $this->bookFixture();
        $user = User::factory()->create(['password' => Hash::make('correcta123')]);
        $highlightId = $this->highlightFor($user, $bookId);

        DB::table('ai_interactions')->insert([
            'user_id' => $user->id, 'question' => '¿…?', 'answer' => '…',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/me', ['password' => 'correcta123'])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('verse_highlights', ['id' => $highlightId]);
        $this->assertDatabaseMissing('highlight_colors', ['user_id' => $user->id]);
        // Las interacciones de IA quedan anonimizadas, no borradas
        $this->assertDatabaseHas('ai_interactions', ['user_id' => null]);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_account_deletion_web_page_is_public(): void
    {
        $this->get('/eliminar-cuenta')
            ->assertOk()
            ->assertSee('Eliminar tu cuenta')
            ->assertSee('Delete your account');

        $this->get('/delete-account')->assertRedirect('/eliminar-cuenta');
    }

    // ── Rate limiting ────────────────────────────────────────────────────────

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'quien@ejemplo.com']);

        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->postJson('/api/login', [
                'email' => 'quien@ejemplo.com', 'password' => 'incorrecta',
            ]);
        }

        $last->assertStatus(429);
    }
}
