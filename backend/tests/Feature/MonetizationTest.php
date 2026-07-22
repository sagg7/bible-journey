<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.revenuecat.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    /** Crea plan publicado + CRS premium y CRS gratuito, cada uno con su nodo. */
    private function planFixture(): array
    {
        $now = now();

        $planId = DB::table('stream_plans')->insertGetId([
            'profile_id' => 'cautious_default', 'locale' => 'es',
            'publication_status' => 'published', 'published_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $premiumCrsId = DB::table('chronological_reading_sets')->insertGetId([
            'source_map' => 'CRS-PRM-001', 'era' => 'Test', 'era_slug' => 'test',
            'title_es' => 'CRS premium', 'is_premium' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $freeCrsId = DB::table('chronological_reading_sets')->insertGetId([
            'source_map' => 'CRS-FREE-001', 'era' => 'Test', 'era_slug' => 'test',
            'title_es' => 'CRS gratuito', 'is_premium' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $premiumNodeId = DB::table('stream_plan_nodes')->insertGetId([
            'plan_id' => $planId, 'crs_id' => $premiumCrsId, 'rank' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $freeNodeId = DB::table('stream_plan_nodes')->insertGetId([
            'plan_id' => $planId, 'crs_id' => $freeCrsId, 'rank' => 2,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return compact('planId', 'premiumCrsId', 'freeCrsId', 'premiumNodeId', 'freeNodeId');
    }

    private function webhook(array $event, ?string $secret = self::WEBHOOK_SECRET)
    {
        return $this->postJson('/api/webhooks/revenuecat', ['event' => $event], array_filter([
            'Authorization' => $secret,
        ]));
    }

    // ── Webhook ──────────────────────────────────────────────────────────────

    public function test_webhook_rejects_wrong_secret(): void
    {
        $this->webhook(['type' => 'INITIAL_PURCHASE', 'app_user_id' => '1'], 'wrong')
            ->assertStatus(401);
    }

    public function test_initial_purchase_grants_premium(): void
    {
        $user = User::factory()->create(['subscription_status' => 'free']);

        $this->webhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $user->id,
            'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
        ])->assertOk();

        $user->refresh();
        $this->assertSame('premium', $user->subscription_status);
        $this->assertTrue($user->hasPremiumAccess());
    }

    public function test_cancellation_keeps_access_until_expiration(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'premium',
            'subscription_expires_at' => now()->addDays(20),
        ]);

        $this->webhook([
            'type' => 'CANCELLATION',
            'app_user_id' => (string) $user->id,
            'expiration_at_ms' => now()->addDays(20)->getTimestampMs(),
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(
            $user->hasPremiumAccess(),
            'Cancelar la renovación no debe revocar el periodo ya pagado'
        );
    }

    public function test_expiration_revokes_premium(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'premium',
            'subscription_expires_at' => now()->addDays(3),
        ]);

        $this->webhook([
            'type' => 'EXPIRATION',
            'app_user_id' => (string) $user->id,
        ])->assertOk();

        $this->assertFalse($user->refresh()->hasPremiumAccess());
    }

    public function test_restored_purchase_regrants_access(): void
    {
        $user = User::factory()->create(['subscription_status' => 'free']);

        // Restore llega como TRANSFER hacia el usuario identificado
        $this->webhook([
            'type' => 'TRANSFER',
            'transferred_to' => [(string) $user->id],
            'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
        ])->assertOk();

        $this->assertTrue($user->refresh()->hasPremiumAccess());
    }

    public function test_non_numeric_app_user_id_does_not_hit_wrong_user(): void
    {
        $user = User::factory()->create(['subscription_status' => 'free']);

        // '{$id}-legacy' castearía a {$id} en MySQL sin el guard ctype_digit
        $this->webhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => $user->id . '-legacy',
        ])->assertOk();

        $this->assertSame('free', $user->refresh()->subscription_status);
    }

    public function test_anonymous_purchase_can_resolve_identified_alias(): void
    {
        $user = User::factory()->create(['subscription_status' => 'free']);

        $this->webhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => '$RCAnonymousID:test-purchase',
            'aliases' => ['$RCAnonymousID:test-purchase', (string) $user->id],
            'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
        ])->assertOk();

        $user->refresh();
        $this->assertSame((string) $user->id, $user->revenuecat_customer_id);
        $this->assertTrue($user->hasPremiumAccess());
    }

    public function test_expired_timestamp_denies_premium_even_with_status(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'premium',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->hasPremiumAccess());
    }

    // ── Gating de contenido ──────────────────────────────────────────────────

    public function test_guest_gets_locked_premium_node_and_open_free_node(): void
    {
        $f = $this->planFixture();

        $this->getJson("/api/v2/stream-plans/{$f['planId']}/nodes/{$f['premiumNodeId']}")
            ->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonMissingPath('blocks');

        $this->getJson("/api/v2/stream-plans/{$f['planId']}/nodes/{$f['freeNodeId']}")
            ->assertOk()
            ->assertJsonPath('locked', false);
    }

    public function test_premium_user_unlocks_premium_node(): void
    {
        $f = $this->planFixture();
        $user = User::factory()->create([
            'subscription_status' => 'premium',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v2/stream-plans/{$f['planId']}/nodes/{$f['premiumNodeId']}")
            ->assertOk()
            ->assertJsonPath('locked', false);
    }

    public function test_expired_user_keeps_free_content_but_loses_premium(): void
    {
        $f = $this->planFixture();
        $user = User::factory()->create([
            'subscription_status' => 'premium',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v2/stream-plans/{$f['planId']}/nodes/{$f['premiumNodeId']}")
            ->assertOk()
            ->assertJsonPath('locked', true);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v2/stream-plans/{$f['planId']}/nodes/{$f['freeNodeId']}")
            ->assertOk()
            ->assertJsonPath('locked', false);
    }
}
