<?php

namespace Database\Seeders;

use App\Models\BiblicalBook;
use Illuminate\Database\Seeder;

class BiblicalBookSeeder extends Seeder
{
    public function run(): void
    {
        // Libros necesarios para la ruta "Vida de David"
        $books = [
            ['slug' => '1-samuel', 'osis_code' => '1Sam', 'testament' => 'OT', 'genre' => 'narrativa', 'traditional_author' => 'Samuel / anónimo', 'canonical_order' => 9,
                'es' => '1 Samuel', 'en' => '1 Samuel'],
            ['slug' => '2-samuel', 'osis_code' => '2Sam', 'testament' => 'OT', 'genre' => 'narrativa', 'traditional_author' => 'anónimo', 'canonical_order' => 10,
                'es' => '2 Samuel', 'en' => '2 Samuel'],
            ['slug' => '1-reyes', 'osis_code' => '1Kgs', 'testament' => 'OT', 'genre' => 'narrativa', 'traditional_author' => 'anónimo', 'canonical_order' => 11,
                'es' => '1 Reyes', 'en' => '1 Kings'],
            ['slug' => '1-cronicas', 'osis_code' => '1Chr', 'testament' => 'OT', 'genre' => 'narrativa', 'traditional_author' => 'cronista', 'canonical_order' => 13,
                'es' => '1 Crónicas', 'en' => '1 Chronicles'],
            ['slug' => 'salmos', 'osis_code' => 'Ps', 'testament' => 'OT', 'genre' => 'poesía', 'traditional_author' => 'David y otros', 'canonical_order' => 19,
                'es' => 'Salmos', 'en' => 'Psalms'],
        ];

        foreach ($books as $b) {
            $book = BiblicalBook::updateOrCreate(
                ['slug' => $b['slug']],
                [
                    'osis_code' => $b['osis_code'],
                    'testament' => $b['testament'],
                    'genre' => $b['genre'],
                    'traditional_author' => $b['traditional_author'],
                    'canonical_order' => $b['canonical_order'],
                ]
            );

            foreach (['es' => $b['es'], 'en' => $b['en']] as $locale => $name) {
                $book->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name, 'review_status' => 'approved']
                );
            }
        }
    }
}
