<?php

namespace App\Console\Commands;

use App\Models\BibleChapter;
use App\Models\BibleVerse;
use App\Models\BiblicalBook;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports a Bible translation from the simple
 * <bible><testament><book number="N"><chapter number="N"><verse number="N">
 * XML format used by github.com/Beblia/Holy-Bible-XML-Format. Book `number`
 * runs 1-66 continuously across both testaments, matching canonical_order.
 *
 * Only use this for translations that are actually licensed for this app —
 * see Translation::can_display_full_text / license_status. As of 2026-07-02
 * that's public-domain KJV only; NVI/RVR1960/RVR1995/TLA/NIV are pending a
 * real license (API.Bible / YouVersion Platform), see HANDOFF.md.
 */
class ImportBibleXml extends Command
{
    protected $signature = 'bible:import-xml
                            {code : Translation code, e.g. KJV}
                            {--url= : Raw XML URL}
                            {--source= : Local XML file path (alternative to --url)}
                            {--dry-run : Parse and report without saving}';

    protected $description = 'Import a translation from the Beblia Holy-Bible-XML-Format schema';

    private const EXPECTED_BOOKS = 66;

    private bool $dryRun = false;

    /** @var array<int,int> canonical_order → biblical_books.id */
    private array $bookIdCache = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $code = strtoupper($this->argument('code'));

        $translation = Translation::where('code', $code)->first();
        if (! $translation) {
            $this->error("Traducción {$code} no existe en la tabla translations. Créala primero (metadata + licencia).");

            return self::FAILURE;
        }

        if (! $translation->is_public_domain && $translation->license_status !== 'licensed') {
            $this->error("{$code} no está marcada como dominio público ni licenciada (license_status={$translation->license_status}). Aborto para no importar texto sin licencia.");

            return self::FAILURE;
        }

        $xmlPath = $this->resolveSource();
        if (! $xmlPath) {
            return self::FAILURE;
        }

        $hash = hash_file('sha256', $xmlPath);
        $this->info("Fuente : {$xmlPath}");
        $this->info("SHA-256: {$hash}");

        $this->bookIdCache = BiblicalBook::whereNotNull('canonical_order')
            ->pluck('id', 'canonical_order')
            ->toArray();

        if (count($this->bookIdCache) < self::EXPECTED_BOOKS) {
            $this->error('biblical_books no tiene los 66 libros cargados todavía (correr bible:import-rva1909 primero).');

            return self::FAILURE;
        }

        $stats = $this->parseAndImport($xmlPath, $translation->id);

        if (! $this->dryRun) {
            $translation->forceFill([
                'source_url' => $this->option('url'),
                'source_file_hash' => $hash,
                'imported_at' => now(),
                'can_display_full_text' => true,
            ])->save();

            DB::statement('
                UPDATE bible_chapters bc
                SET verse_count = (
                    SELECT COUNT(DISTINCT bv.verse_number)
                    FROM bible_verses bv
                    WHERE bv.chapter_id = bc.id AND bv.translation_id = ?
                )
                WHERE EXISTS (SELECT 1 FROM bible_verses bv2 WHERE bv2.chapter_id = bc.id AND bv2.translation_id = ?)
            ', [$translation->id, $translation->id]);
        }

        $this->newLine();
        $this->info("Listo. Libros={$stats['books']} Capítulos={$stats['chapters']} Versículos={$stats['verses']}");

        return self::SUCCESS;
    }

    private function resolveSource(): ?string
    {
        if ($source = $this->option('source')) {
            return file_exists($source) ? realpath($source) : null;
        }

        $url = $this->option('url');
        if (! $url) {
            $this->error('Pasa --url=<raw xml url> o --source=<archivo local>.');

            return null;
        }

        $this->info("Descargando {$url} ...");
        $response = Http::timeout(60)->get($url);
        if (! $response->successful()) {
            $this->error('Descarga falló: HTTP ' . $response->status());

            return null;
        }

        $tmpPath = storage_path('app/imports/' . Str::slug($this->argument('code')) . '.xml');
        if (! is_dir(dirname($tmpPath))) {
            mkdir(dirname($tmpPath), 0755, true);
        }
        file_put_contents($tmpPath, $response->body());

        return $tmpPath;
    }

    private function parseAndImport(string $filePath, int $translationId): array
    {
        $stats = ['books' => 0, 'chapters' => 0, 'verses' => 0];

        $reader = new \XMLReader();
        if (! $reader->open($filePath)) {
            $this->error('No se pudo abrir el XML.');

            return $stats;
        }

        $currentBookNumber = null;
        $currentBookId = null;
        $currentChNum = null;
        $currentChId = null;
        /** @var array<int,int> */
        $chapterIdCache = [];

        $verseBatch = [];
        $batchSize = 500;

        $bar = $this->output->createProgressBar(self::EXPECTED_BOOKS);
        $bar->start();

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            switch ($reader->localName) {
                case 'book':
                    $currentBookNumber = (int) $reader->getAttribute('number');
                    $currentBookId = $this->bookIdCache[$currentBookNumber] ?? null;
                    $chapterIdCache = [];
                    if ($currentBookId) {
                        $stats['books']++;
                        $bar->advance();
                    }
                    break;

                case 'chapter':
                    if (! $currentBookId) {
                        break;
                    }
                    $currentChNum = (int) $reader->getAttribute('number');
                    if ($currentChNum < 1) {
                        break;
                    }

                    if (! $this->dryRun) {
                        $ch = BibleChapter::firstOrCreate(
                            ['biblical_book_id' => $currentBookId, 'chapter_number' => $currentChNum],
                            ['verse_count' => 0]
                        );
                        $currentChId = $ch->id;
                        $chapterIdCache[$currentChNum] = $ch->id;
                    }
                    $stats['chapters']++;
                    break;

                case 'verse':
                    if (! $currentBookId || $currentChNum === null) {
                        break;
                    }
                    $vNum = (int) $reader->getAttribute('number');
                    if ($vNum < 1) {
                        break;
                    }
                    $text = trim(preg_replace('/\s+/', ' ', $reader->readString()) ?? '');
                    if ($text === '') {
                        break;
                    }

                    $chId = $chapterIdCache[$currentChNum] ?? $currentChId;
                    if ($chId) {
                        $verseBatch[] = [
                            'chapter_id' => $chId,
                            'verse_number' => $vNum,
                            'translation_id' => $translationId,
                            'text' => $text,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $stats['verses']++;

                        if (! $this->dryRun && count($verseBatch) >= $batchSize) {
                            $this->flushVerseBatch($verseBatch);
                            $verseBatch = [];
                        }
                    }
                    break;
            }
        }

        $reader->close();

        if (! $this->dryRun && ! empty($verseBatch)) {
            $this->flushVerseBatch($verseBatch);
        }

        $bar->finish();
        $this->newLine();

        return $stats;
    }

    private function flushVerseBatch(array $batch): void
    {
        DB::table('bible_verses')->upsert(
            $batch,
            ['chapter_id', 'verse_number', 'translation_id'],
            ['text', 'updated_at']
        );
    }
}
