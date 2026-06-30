<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $versions = [
            // Dominio público → se muestra texto completo
            [
                'code' => 'RVA1909', 'language' => 'es', 'name' => 'Reina-Valera Antigua (1909)',
                'is_public_domain' => true, 'license_status' => 'none', 'can_display_full_text' => true,
                'attribution' => 'Dominio público', 'sort_order' => 1,
            ],
            [
                'code' => 'WEB', 'language' => 'en', 'name' => 'World English Bible',
                'is_public_domain' => true, 'license_status' => 'none', 'can_display_full_text' => true,
                'attribution' => 'Public domain', 'sort_order' => 2,
            ],
            [
                'code' => 'KJV', 'language' => 'en', 'name' => 'King James Version',
                'is_public_domain' => true, 'license_status' => 'none', 'can_display_full_text' => true,
                'attribution' => 'Public domain', 'sort_order' => 3,
            ],
            // Protegidas → solo referencia hasta obtener licencia
            [
                'code' => 'NVI', 'language' => 'es', 'name' => 'Nueva Versión Internacional',
                'is_public_domain' => false, 'license_status' => 'pending', 'can_display_full_text' => false,
                'attribution' => '© Biblica, Inc. — licencia pendiente', 'sort_order' => 4,
            ],
            [
                'code' => 'RVR60', 'language' => 'es', 'name' => 'Reina-Valera 1960',
                'is_public_domain' => false, 'license_status' => 'pending', 'can_display_full_text' => false,
                'attribution' => '© Sociedades Bíblicas Unidas — licencia pendiente', 'sort_order' => 5,
            ],
            [
                'code' => 'NIV', 'language' => 'en', 'name' => 'New International Version',
                'is_public_domain' => false, 'license_status' => 'pending', 'can_display_full_text' => false,
                'attribution' => '© Biblica, Inc. — license pending', 'sort_order' => 6,
            ],
        ];

        foreach ($versions as $v) {
            Translation::updateOrCreate(['code' => $v['code']], $v);
        }
    }
}
