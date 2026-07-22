<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BiblicalBookSeeder;
use Database\Seeders\DavidRouteSeeder;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_resource_index_pages_load(): void
    {
        $this->seed([TranslationSeeder::class, BiblicalBookSeeder::class, DavidRouteSeeder::class]);
        $admin = $this->admin();

        $slugs = [
            'translations', 'historical-events', 'characters', 'locations',
            'crs', 'stream-plans', 'institutions', 'institution-members', 'users',
        ];

        foreach ($slugs as $slug) {
            $this->actingAs($admin)->get("/admin/{$slug}")->assertOk();
        }
    }

    public function test_resource_create_pages_load(): void
    {
        // Las páginas de creación instancian el esquema completo del formulario
        // (incluidos repeaters anidados y closures de opciones).
        $this->seed([TranslationSeeder::class, BiblicalBookSeeder::class, DavidRouteSeeder::class]);
        $admin = $this->admin();

        // stream-plans e institution-members no tienen página create (se
        // generan vía CLI compile / relación), por eso no aparecen aquí.
        $slugs = [
            'translations', 'historical-events', 'characters', 'locations',
            'crs', 'institutions', 'users',
        ];

        foreach ($slugs as $slug) {
            $this->actingAs($admin)->get("/admin/{$slug}/create")->assertOk();
        }
    }

    public function test_event_edit_page_loads_with_nested_content(): void
    {
        $this->seed([TranslationSeeder::class, BiblicalBookSeeder::class, DavidRouteSeeder::class]);
        $admin = $this->admin();

        $event = \App\Models\HistoricalEvent::where('slug', 'david-en-gat')->firstOrFail();

        $this->actingAs($admin)->get("/admin/historical-events/{$event->id}/edit")->assertOk();
    }
}
