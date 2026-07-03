<?php

namespace App\Services\Bible;

use App\Models\BibleChapter;
use App\Models\BiblicalBook;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

/**
 * Parses and imports a Bible translation from the simple
 * <bible><testament><book number="N"><chapter number="N"><verse number="N">
 * XML format used by github.com/Beblia/Holy-Bible-XML-Format. Book `number`
 * runs 1-66 continuously across both testaments, matching canonical_order.
 *
 * Shared by the `bible:import-xml` console command and the Filament web importer —
 * both must go through here so the license safety gate can't be bypassed by either entry point.
 */
class BibleXmlImportService
{
    private const EXPECTED_BOOKS = 66;

    /**
     * @param  callable(int $bookNumber, ?string $slug): void|null  $onBook  called once per <book> encountered
     * @return array{books:int, chapters:int, verses:int}
     */
    public function import(Translation $translation, string $xmlPath, ?string $sourceUrl = null, bool $dryRun = false, ?callable $onBook = null): array
    {
        $this->assertLicensed($translation);

        $bookIdCache = BiblicalBook::whereNotNull('canonical_order')
            ->pluck('id', 'canonical_order')
            ->toArray();

        if (count($bookIdCache) < self::EXPECTED_BOOKS) {
            throw new BibleXmlImportException('biblical_books no tiene los 66 libros cargados todavía (correr bible:import-rva1909 primero).');
        }

        $stats = $this->parseAndImport($xmlPath, $translation->id, $bookIdCache, $dryRun, $onBook);

        if (! $dryRun) {
            $translation->forceFill([
                'source_url' => $sourceUrl,
                'source_file_hash' => hash_file('sha256', $xmlPath),
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

        return $stats;
    }

    private function assertLicensed(Translation $translation): void
    {
        if (! $translation->is_public_domain && $translation->license_status->value !== 'licensed') {
            throw new BibleXmlImportException(
                "{$translation->code} no está marcada como dominio público ni licenciada (license_status={$translation->license_status->value}). Aborto para no importar texto sin licencia."
            );
        }
    }

    /**
     * @param  array<int,int>  $bookIdCache
     * @return array{books:int, chapters:int, verses:int}
     */
    private function parseAndImport(string $filePath, int $translationId, array $bookIdCache, bool $dryRun, ?callable $onBook): array
    {
        $stats = ['books' => 0, 'chapters' => 0, 'verses' => 0];

        $reader = new \XMLReader();
        if (! $reader->open($filePath)) {
            throw new BibleXmlImportException('No se pudo abrir el XML.');
        }

        $currentBookNumber = null;
        $currentBookId = null;
        $currentChNum = null;
        $currentChId = null;
        /** @var array<int,int> */
        $chapterIdCache = [];

        $verseBatch = [];
        $batchSize = 500;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            switch ($reader->localName) {
                case 'book':
                    $currentBookNumber = (int) $reader->getAttribute('number');
                    $currentBookId = $bookIdCache[$currentBookNumber] ?? null;
                    $chapterIdCache = [];
                    if ($currentBookId) {
                        $stats['books']++;
                        if ($onBook) {
                            $onBook($currentBookNumber, BiblicalBook::find($currentBookId)?->slug);
                        }
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

                    if (! $dryRun) {
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

                        if (! $dryRun && count($verseBatch) >= $batchSize) {
                            $this->flushVerseBatch($verseBatch);
                            $verseBatch = [];
                        }
                    }
                    break;
            }
        }

        $reader->close();

        if (! $dryRun && ! empty($verseBatch)) {
            $this->flushVerseBatch($verseBatch);
        }

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
