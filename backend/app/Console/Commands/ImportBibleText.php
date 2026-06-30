<?php

namespace App\Console\Commands;

use App\Models\BiblicalBook;
use App\Models\Passage;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Importa texto bíblico desde un archivo JSON (mecanismo general para volcados
 * de dominio público descargados). Crea/actualiza pasajes y su texto.
 *
 * Formato del JSON: array de objetos
 *   {
 *     "book_slug": "1-samuel",
 *     "reference_label": "1 Samuel 21:1-9",
 *     "chapter_start": 21, "verse_start": 1, "chapter_end": 21, "verse_end": 9,
 *     "content": "1. ...\n2. ...",
 *     "verses": [{ "v": 1, "t": "..." }]   // opcional
 *   }
 *
 * Uso:  php artisan bible:import RVA1909 ruta/al/archivo.json
 */
class ImportBibleText extends Command
{
    protected $signature = 'bible:import {translation : Código de la traducción (ej. RVA1909)} {file : Ruta al archivo JSON}';

    protected $description = 'Importa texto bíblico de dominio público desde un archivo JSON';

    public function handle(): int
    {
        $translation = Translation::where('code', $this->argument('translation'))->first();
        if (! $translation) {
            $this->error("No existe la traducción {$this->argument('translation')}.");

            return self::FAILURE;
        }

        if (! $translation->is_public_domain) {
            $this->error("{$translation->code} no es de dominio público; no se puede importar texto completo.");

            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (! File::exists($path)) {
            $this->error("No se encontró el archivo {$path}.");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);
        if (! is_array($rows)) {
            $this->error('El archivo no contiene un JSON válido (se esperaba un array).');

            return self::FAILURE;
        }

        $imported = 0;
        foreach ($rows as $row) {
            $book = BiblicalBook::where('slug', $row['book_slug'] ?? '')->first();
            if (! $book) {
                $this->warn("Libro no encontrado: {$row['book_slug']}");

                continue;
            }

            $passage = Passage::updateOrCreate(
                ['biblical_book_id' => $book->id, 'reference_label' => $row['reference_label']],
                [
                    'chapter_start' => $row['chapter_start'],
                    'verse_start' => $row['verse_start'] ?? null,
                    'chapter_end' => $row['chapter_end'] ?? null,
                    'verse_end' => $row['verse_end'] ?? null,
                ]
            );

            $passage->texts()->updateOrCreate(
                ['translation_id' => $translation->id],
                ['content' => $row['content'] ?? '', 'verses' => $row['verses'] ?? null]
            );

            $imported++;
        }

        $this->info("Importados {$imported} pasajes para {$translation->code}.");

        return self::SUCCESS;
    }
}
