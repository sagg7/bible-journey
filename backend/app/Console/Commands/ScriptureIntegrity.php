<?php

namespace App\Console\Commands;

use App\Models\BiblicalBook;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Valida la integridad estructural del corpus bíblico y genera/verifica un
 * manifiesto versionado con hashes SHA-256 por corpus, libro y capítulo.
 *
 * Modos:
 *   php artisan scripture:manifest            → valida y escribe el manifiesto
 *   php artisan scripture:manifest --check    → valida y compara contra el
 *                                                manifiesto existente; sale con
 *                                                código 1 si hay diferencias.
 *
 * El manifiesto es el mecanismo de detección de modificaciones accidentales
 * del texto bíblico (principio: el corpus es contenido protegido). Nunca
 * "repara" texto: solo reporta.
 */
class ScriptureIntegrity extends Command
{
    protected $signature = 'scripture:manifest
                            {--check : Comparar contra el manifiesto existente en vez de escribirlo}
                            {--path= : Ruta del manifiesto (default: data/manifests/scripture-manifest.json en la raíz del repo)}';

    protected $description = 'Valida el corpus bíblico y genera/verifica el manifiesto de hashes SHA-256';

    public function handle(): int
    {
        $path = $this->option('path')
            ?: dirname(base_path()) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'manifests' . DIRECTORY_SEPARATOR . 'scripture-manifest.json';

        $failures = $this->validateStructure();

        foreach ($failures as $f) {
            $this->error("  ✗ {$f}");
        }
        if (empty($failures)) {
            $this->info('  ✓ Validaciones estructurales: sin problemas');
        }

        $manifest = $this->buildManifest();

        if ($this->option('check')) {
            return $this->compare($manifest, $path, $failures);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents(
            $path,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->info("Manifiesto escrito: {$path}");

        return empty($failures) ? self::SUCCESS : self::FAILURE;
    }

    /** @return string[] lista de problemas encontrados */
    private function validateStructure(): array
    {
        $problems = [];

        $books = (int) BiblicalBook::count();
        if ($books !== 66) {
            $problems[] = "biblical_books = {$books}, esperado 66";
        }

        $chapters = (int) DB::table('bible_chapters')->count();
        if ($chapters !== 1189) {
            $problems[] = "bible_chapters = {$chapters}, esperado 1189";
        }

        $dupBooks = DB::table('biblical_books')
            ->select('osis_code')->groupBy('osis_code')->havingRaw('COUNT(*) > 1')->count();
        if ($dupBooks > 0) {
            $problems[] = "{$dupBooks} osis_code duplicados en biblical_books";
        }

        $dupChapters = DB::table('bible_chapters')
            ->select('biblical_book_id', 'chapter_number')
            ->groupBy('biblical_book_id', 'chapter_number')->havingRaw('COUNT(*) > 1')->count();
        if ($dupChapters > 0) {
            $problems[] = "{$dupChapters} capítulos duplicados";
        }

        $chapterGaps = DB::table('bible_chapters as c')
            ->where('c.chapter_number', '>', 1)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('bible_chapters as p')
                    ->whereColumn('p.biblical_book_id', 'c.biblical_book_id')
                    ->whereRaw('p.chapter_number = c.chapter_number - 1');
            })->count();
        if ($chapterGaps > 0) {
            $problems[] = "{$chapterGaps} saltos en numeración de capítulos";
        }

        foreach ($this->importedTranslations() as $t) {
            $dupVerses = DB::table('bible_verses')
                ->where('translation_id', $t->id)
                ->select('chapter_id', 'verse_number')
                ->groupBy('chapter_id', 'verse_number')->havingRaw('COUNT(*) > 1')->count();
            if ($dupVerses > 0) {
                $problems[] = "[{$t->code}] {$dupVerses} versículos duplicados";
            }

            $empty = DB::table('bible_verses')
                ->where('translation_id', $t->id)
                ->where(fn ($q) => $q->whereNull('text')->orWhereRaw("TRIM(text) = ''"))
                ->count();
            if ($empty > 0) {
                $problems[] = "[{$t->code}] {$empty} versículos vacíos";
            }

            $gaps = DB::table('bible_verses as v')
                ->where('v.translation_id', $t->id)
                ->where('v.verse_number', '>', 1)
                ->whereNotExists(function ($q) use ($t) {
                    $q->select(DB::raw(1))->from('bible_verses as p')
                        ->where('p.translation_id', $t->id)
                        ->whereColumn('p.chapter_id', 'v.chapter_id')
                        ->whereRaw('p.verse_number = v.verse_number - 1');
                })->count();
            if ($gaps > 0) {
                $problems[] = "[{$t->code}] {$gaps} saltos en numeración de versículos";
            }

            $html = DB::table('bible_verses')
                ->where('translation_id', $t->id)
                ->whereRaw("text REGEXP '<[a-zA-Z/][^>]*>'")
                ->count();
            if ($html > 0) {
                $problems[] = "[{$t->code}] {$html} versículos con HTML inesperado";
            }

            $badEncoding = DB::table('bible_verses')
                ->where('translation_id', $t->id)
                ->where(function ($q) {
                    $q->where('text', 'like', "%\u{FFFD}%")          // replacement char
                      ->orWhereRaw("CONVERT(text USING binary) LIKE CONCAT('%', 0xC383, '%')"); // mojibake Ã
                })->count();
            if ($badEncoding > 0) {
                $problems[] = "[{$t->code}] {$badEncoding} versículos con encoding sospechoso";
            }
        }

        return $problems;
    }

    private function buildManifest(): array
    {
        $manifest = [
            'schema'       => 'bible-journey/scripture-manifest@1',
            'generated_at' => now()->toIso8601String(),
            'algorithm'    => 'sha256',
            'translations' => [],
        ];

        foreach ($this->importedTranslations() as $t) {
            $bookHashes = [];
            $chapterHashes = [];
            $corpusCtx = hash_init('sha256');
            $verseTotal = 0;

            $books = BiblicalBook::orderBy('canonical_order')->get();
            foreach ($books as $book) {
                $bookCtx = hash_init('sha256');

                $rows = DB::table('bible_verses as v')
                    ->join('bible_chapters as c', 'c.id', '=', 'v.chapter_id')
                    ->where('c.biblical_book_id', $book->id)
                    ->where('v.translation_id', $t->id)
                    ->orderBy('c.chapter_number')->orderBy('v.verse_number')
                    ->get(['c.chapter_number', 'v.verse_number', 'v.text']);

                $byChapter = [];
                foreach ($rows as $r) {
                    $line = "{$book->osis_code}|{$r->chapter_number}|{$r->verse_number}|{$r->text}\n";
                    hash_update($corpusCtx, $line);
                    hash_update($bookCtx, $line);
                    $byChapter[$r->chapter_number][] = $line;
                    $verseTotal++;
                }

                foreach ($byChapter as $chNum => $lines) {
                    $chapterHashes["{$book->osis_code}.{$chNum}"] = [
                        'hash'   => hash('sha256', implode('', $lines)),
                        'verses' => count($lines),
                    ];
                }

                if (! empty($byChapter)) {
                    $bookHashes[$book->osis_code] = [
                        'hash'     => hash_final($bookCtx),
                        'chapters' => count($byChapter),
                    ];
                }
            }

            $manifest['translations'][$t->code] = [
                'name'         => $t->name,
                'language'     => $t->language,
                'license'      => $t->license_label ?? ($t->is_public_domain ? 'Public Domain' : 'unverified'),
                'verse_count'  => $verseTotal,
                'corpus_hash'  => hash_final($corpusCtx),
                'books'        => $bookHashes,
                'chapters'     => $chapterHashes,
            ];
        }

        return $manifest;
    }

    private function compare(array $current, string $path, array $structuralFailures): int
    {
        if (! is_file($path)) {
            $this->error("No existe manifiesto en {$path} — genera uno primero (sin --check).");
            return self::FAILURE;
        }

        $stored = json_decode(file_get_contents($path), true);
        $diffs = [];

        foreach ($current['translations'] as $code => $data) {
            $old = $stored['translations'][$code] ?? null;
            if (! $old) {
                $diffs[] = "[{$code}] traducción nueva (no está en el manifiesto)";
                continue;
            }
            if ($old['corpus_hash'] !== $data['corpus_hash']) {
                $diffs[] = "[{$code}] corpus_hash cambió";
                foreach ($data['chapters'] as $ref => $info) {
                    $oldCh = $old['chapters'][$ref] ?? null;
                    if (! $oldCh) {
                        $diffs[] = "[{$code}] {$ref}: capítulo nuevo";
                    } elseif ($oldCh['hash'] !== $info['hash']) {
                        $diffs[] = "[{$code}] {$ref}: texto modificado (versos {$oldCh['verses']} → {$info['verses']})";
                    }
                }
                foreach (array_diff_key($old['chapters'], $data['chapters']) as $ref => $_) {
                    $diffs[] = "[{$code}] {$ref}: capítulo desaparecido";
                }
            }
        }
        foreach (array_diff_key($stored['translations'] ?? [], $current['translations']) as $code => $_) {
            $diffs[] = "[{$code}] traducción desaparecida de la BD";
        }

        foreach ($diffs as $d) {
            $this->error("  ✗ {$d}");
        }

        if (empty($diffs) && empty($structuralFailures)) {
            $this->info('  ✓ Corpus íntegro: coincide con el manifiesto.');
            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Verificación falló: %d diferencia(s) de hash, %d problema(s) estructural(es). NO se modificó nada — revisar docs/audits/scripture-integrity-report.md.',
            count($diffs),
            count($structuralFailures)
        ));

        return self::FAILURE;
    }

    /** @return \Illuminate\Support\Collection<int, Translation> */
    private function importedTranslations()
    {
        $ids = DB::table('bible_verses')->distinct()->pluck('translation_id');

        return Translation::whereIn('id', $ids)->orderBy('id')->get();
    }
}
