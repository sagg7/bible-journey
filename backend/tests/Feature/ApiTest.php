<?php

namespace Tests\Feature;

use App\Models\HistoricalEvent;
use App\Models\Passage;
use App\Models\Translation;
use App\Models\User;
use Database\Seeders\BiblicalBookSeeder;
use Database\Seeders\DavidRouteSeeder;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TranslationSeeder::class, BiblicalBookSeeder::class, DavidRouteSeeder::class]);
    }

    public function test_routes_index_returns_localized_titles(): void
    {
        $this->getJson('/api/routes?locale=es')
            ->assertOk()
            ->assertJsonFragment(['title' => 'La vida de David']);

        $this->getJson('/api/routes?locale=en')
            ->assertOk()
            ->assertJsonFragment(['title' => 'The Life of David']);
    }

    public function test_free_event_returns_full_payload_with_psalm_certainty(): void
    {
        Sanctum::actingAs(User::factory()->create(['subscription_status' => 'premium']));

        $this->getJson('/api/events/david-en-gat?locale=es')
            ->assertOk()
            ->assertJsonPath('data.title', 'David en Gat')
            ->assertJsonPath('data.psalm_connections.0.psalm_reference', 'Salmo 34')
            ->assertJsonPath('data.psalm_connections.0.certainty_label', 'Alta confianza');
    }

    public function test_protected_translation_returns_reference_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['subscription_status' => 'premium']));

        $this->getJson('/api/events/david-en-gat?locale=es&translation=NVI')
            ->assertOk()
            ->assertJsonPath('data.passages.0.text_available', false)
            ->assertJsonPath('data.passages.0.reference_only_reason', 'license_required');
    }

    public function test_public_domain_translation_returns_text_when_imported(): void
    {
        Sanctum::actingAs(User::factory()->create(['subscription_status' => 'premium']));

        $rva = Translation::where('code', 'RVA1909')->first();
        $passage = Passage::where('reference_label', '1 Samuel 21:10-15')->firstOrFail();
        $passage->texts()->create(['translation_id' => $rva->id, 'content' => 'Texto de prueba.']);

        $this->getJson('/api/events/david-en-gat?locale=es&translation=RVA1909')
            ->assertOk()
            ->assertJsonPath('data.passages.0.text_available', true)
            ->assertJsonPath('data.passages.0.text', 'Texto de prueba.');
    }

    public function test_premium_event_is_locked_for_guests(): void
    {
        $this->getJson('/api/events/david-y-jonatan?locale=es')
            ->assertOk()
            ->assertJsonPath('data.locked', true);
    }

    public function test_register_login_and_progress_flow(): void
    {
        $register = $this->postJson('/api/register', [
            'name' => 'Test', 'email' => 'u@test.com', 'password' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'u@test.com')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/progress/complete', [
            'route_slug' => 'vida-de-david',
            'event_slug' => 'samuel-unge-a-david',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.completed_count', 1)
            ->assertJsonPath('data.streak_count', 1);
    }

    public function test_ezra_returns_503_without_api_key(): void
    {
        config(['ezra.api_key' => null]);
        $user = User::factory()->create(['subscription_status' => 'premium']);
        Sanctum::actingAs($user);

        $this->postJson('/api/events/samuel-unge-a-david/ask', ['question' => '¿Quién ungió a David?'])
            ->assertStatus(503);
    }
}
