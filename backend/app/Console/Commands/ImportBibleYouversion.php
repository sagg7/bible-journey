<?php

namespace App\Console\Commands;

use App\Models\BibleChapter;
use App\Models\BiblicalBook;
use App\Models\Translation;
use App\Support\UsfmBookCodes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports a Bible translation verse-by-verse from the YouVersion Platform API
 * (api.youversion.com), which only exposes one verse per request
 * (/v1/bibles/{id}/passages/{USFM}.{chapter}.{verse}) with no batching.
 *
 * Only use this for translations that are actually licensed for this app —
 * see Translation::can_display_full_text / license_status.
 */
class ImportBibleYouversion extends Command
{
    protected $signature = 'bible:import-youversion
                            {code : Translation code, e.g. BSB}
                            {--bible-id= : YouVersion Platform bible id, e.g. 3034}
                            {--force : Re-import chapters that already have verses}
                            {--limit= : Stop after N books (for testing)}';

    protected $description = 'Import a translation verse-by-verse from the YouVersion Platform API';

    private const THROTTLE_MICROSECONDS = 300_000;

    private const MAX_RATE_LIMIT_RETRIES = 5;

    public function handle(): int
    {
        $code = strtoupper($this->argument('code'));

        $translation = Translation::where('code', $code)->first();
        if (! $translation) {
            $this->error("Traducción {$code} no existe en la tabla translations. Créala primero (metadata + licencia).");

            return self::FAILURE;
        }

        if (! $translation->is_public_domain && $translation->license_status->value !== 'licensed') {
            $this->error("{$code} no está marcada como dominio público ni licenciada (license_status={$translation->license_status->value}). Aborto para no importar texto sin licencia.");

            return self::FAILURE;
        }

        $bibleId = $this->option('bible-id');
        if (! $bibleId) {
            $this->error('Pasa --bible-id=<id de YouVersion Platform>.');

            return self::FAILURE;
        }

        $appKey = config('services.youversion.app_key');
        if (! $appKey) {
            $this->error('YOUVERSION_APP_KEY no está configurada en .env.');

            return self::FAILURE;
        }

        $books = BiblicalBook::whereNotNull('canonical_order')
            ->orderBy('canonical_order')
            ->get();

        if ($limit = $this->option('limit')) {
            $books = $books->take((int) $limit);
        }

        $totalVerses = 0;
        $totalChapters = 0;

        foreach ($books as $book) {
            $usfm = UsfmBookCodes::forCanonicalOrder($book->canonical_order);
            if (! $usfm) {
                $this->warn("Sin código USFM para {$book->slug}, salto.");
                continue;
            }

            $chapters = BibleChapter::where('biblical_book_id', $book->id)
                ->orderBy('chapter_number')
                ->get();

            $this->info("{$book->slug} ({$usfm}) — {$chapters->count()} capítulos");
            $bar = $this->output->createProgressBar($chapters->count());
            $bar->start();

            foreach ($chapters as $chapter) {
                $alreadyImported = DB::table('bible_verses')
                    ->where('chapter_id', $chapter->id)
                    ->where('translation_id', $translation->id)
                    ->exists();

                if ($alreadyImported && ! $this->option('force')) {
                    $bar->advance();
                    continue;
                }

                [$verses, $complete] = $this->fetchChapterVerses($appKey, $bibleId, $usfm, $chapter->chapter_number);

                if (! $complete) {
                    $this->warn("  {$usfm}.{$chapter->chapter_number} no se completó, no se guarda (se reintentará en la próxima corrida).");
                    $bar->advance();
                    continue;
                }

                if (! empty($verses)) {
                    $batch = [];
                    foreach ($verses as $verseNumber => $text) {
                        $batch[] = [
                            'chapter_id' => $chapter->id,
                            'verse_number' => $verseNumber,
                            'translation_id' => $translation->id,
                            'text' => $text,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    DB::table('bible_verses')->upsert(
                        $batch,
                        ['chapter_id', 'verse_number', 'translation_id'],
                        ['text', 'updated_at']
                    );
                    $totalVerses += count($batch);

                    DB::table('bible_chapters')
                        ->where('id', $chapter->id)
                        ->update(['verse_count' => DB::raw('GREATEST(verse_count, '.count($batch).')')]);
                }

                $totalChapters++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $translation->forceFill([
            'source_url' => rtrim(config('services.youversion.api_base'), '/')."/bibles/{$bibleId}",
            'imported_at' => now(),
            'can_display_full_text' => true,
        ])->save();

        $this->newLine();
        $this->info("Listo. Capítulos procesados={$totalChapters} Versículos importados={$totalVerses}");

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int,string>, 1: bool} [verse_number => text, completed_cleanly]
     */
    private function fetchChapterVerses(string $appKey, string $bibleId, string $usfm, int $chapterNumber): array
    {
        $verses = [];
        $verseNumber = 1;
        $rateLimitRetries = 0;

        while (true) {
            usleep(self::THROTTLE_MICROSECONDS);

            $ref = "{$usfm}.{$chapterNumber}.{$verseNumber}";
            $url = rtrim(config('services.youversion.api_base'), '/')."/bibles/{$bibleId}/passages/{$ref}";

            $response = Http::withHeaders(['X-YVP-App-Key' => $appKey])
                ->timeout(20)
                ->get($url, ['content_types[]' => 'text']);

            if ($response->status() === 404) {
                return [$verses, true];
            }

            if ($response->status() === 429) {
                $rateLimitRetries++;
                if ($rateLimitRetries > self::MAX_RATE_LIMIT_RETRIES) {
                    $this->warn("  Rate limit persistente en {$ref} tras ".self::MAX_RATE_LIMIT_RETRIES.' reintentos, corto.');

                    return [$verses, false];
                }
                $wait = (int) ($response->header('Retry-After') ?: 300);
                $this->warn("  Rate limit en {$ref}, espero {$wait}s (intento {$rateLimitRetries}/".self::MAX_RATE_LIMIT_RETRIES.')...');
                sleep($wait + 2);
                continue;
            }

            if (! $response->successful()) {
                $this->warn("  HTTP {$response->status()} en {$ref}, corto capítulo.");

                return [$verses, false];
            }

            $rateLimitRetries = 0;

            $text = trim((string) $response->json('content'));
            if ($text === '') {
                return [$verses, true];
            }

            $verses[$verseNumber] = $text;
            $verseNumber++;

            if ($verseNumber > 200) {
                $this->warn("  {$usfm}.{$chapterNumber} superó 200 versículos, corto por seguridad.");

                return [$verses, false];
            }
        }
    }
}
