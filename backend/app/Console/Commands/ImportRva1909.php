<?php

namespace App\Console\Commands;

use App\Models\BiblicalBook;
use App\Models\BibleChapter;
use App\Models\BibleVerse;
use App\Models\ReadingBlock;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportRva1909 extends Command
{
    protected $signature = 'bible:import-rva1909
                            {--source= : Ruta al archivo USFX XML (o ZIP)}
                            {--translation=RVA1909 : Código de traducción}
                            {--skip-resolve : No resolver rangos de reading_blocks}
                            {--dry-run : Mostrar conteos sin guardar}';

    protected $description = 'Importa la Reina-Valera 1909 desde USFX XML a bible_chapters + bible_verses';

    // Expected canonical totals
    private const EXPECTED_BOOKS    = 66;
    private const EXPECTED_CHAPTERS = 1189;

    // USFX book code → canonical order (1-based) + names + chapters
    private const BOOK_CATALOG = [
        'GEN' => ['order'=>1,  'name_es'=>'Génesis',                  'name_en'=>'Genesis',          'chapters'=>50,  'testament'=>'OT', 'slug'=>'genesis'],
        'EXO' => ['order'=>2,  'name_es'=>'Éxodo',                    'name_en'=>'Exodus',           'chapters'=>40,  'testament'=>'OT', 'slug'=>'exodo'],
        'LEV' => ['order'=>3,  'name_es'=>'Levítico',                 'name_en'=>'Leviticus',        'chapters'=>27,  'testament'=>'OT', 'slug'=>'levitico'],
        'NUM' => ['order'=>4,  'name_es'=>'Números',                  'name_en'=>'Numbers',          'chapters'=>36,  'testament'=>'OT', 'slug'=>'numeros'],
        'DEU' => ['order'=>5,  'name_es'=>'Deuteronomio',             'name_en'=>'Deuteronomy',      'chapters'=>34,  'testament'=>'OT', 'slug'=>'deuteronomio'],
        'JOS' => ['order'=>6,  'name_es'=>'Josué',                    'name_en'=>'Joshua',           'chapters'=>24,  'testament'=>'OT', 'slug'=>'josue'],
        'JDG' => ['order'=>7,  'name_es'=>'Jueces',                   'name_en'=>'Judges',           'chapters'=>21,  'testament'=>'OT', 'slug'=>'jueces'],
        'RUT' => ['order'=>8,  'name_es'=>'Rut',                      'name_en'=>'Ruth',             'chapters'=>4,   'testament'=>'OT', 'slug'=>'rut'],
        '1SA' => ['order'=>9,  'name_es'=>'1 Samuel',                 'name_en'=>'1 Samuel',         'chapters'=>31,  'testament'=>'OT', 'slug'=>'1-samuel'],
        '2SA' => ['order'=>10, 'name_es'=>'2 Samuel',                 'name_en'=>'2 Samuel',         'chapters'=>24,  'testament'=>'OT', 'slug'=>'2-samuel'],
        '1KI' => ['order'=>11, 'name_es'=>'1 Reyes',                  'name_en'=>'1 Kings',          'chapters'=>22,  'testament'=>'OT', 'slug'=>'1-reyes'],
        '2KI' => ['order'=>12, 'name_es'=>'2 Reyes',                  'name_en'=>'2 Kings',          'chapters'=>25,  'testament'=>'OT', 'slug'=>'2-reyes'],
        '1CH' => ['order'=>13, 'name_es'=>'1 Crónicas',               'name_en'=>'1 Chronicles',     'chapters'=>29,  'testament'=>'OT', 'slug'=>'1-cronicas'],
        '2CH' => ['order'=>14, 'name_es'=>'2 Crónicas',               'name_en'=>'2 Chronicles',     'chapters'=>36,  'testament'=>'OT', 'slug'=>'2-cronicas'],
        'EZR' => ['order'=>15, 'name_es'=>'Esdras',                   'name_en'=>'Ezra',             'chapters'=>10,  'testament'=>'OT', 'slug'=>'esdras'],
        'NEH' => ['order'=>16, 'name_es'=>'Nehemías',                 'name_en'=>'Nehemiah',         'chapters'=>13,  'testament'=>'OT', 'slug'=>'nehemias'],
        'EST' => ['order'=>17, 'name_es'=>'Ester',                    'name_en'=>'Esther',           'chapters'=>10,  'testament'=>'OT', 'slug'=>'ester'],
        'JOB' => ['order'=>18, 'name_es'=>'Job',                      'name_en'=>'Job',              'chapters'=>42,  'testament'=>'OT', 'slug'=>'job'],
        'PSA' => ['order'=>19, 'name_es'=>'Salmos',                   'name_en'=>'Psalms',           'chapters'=>150, 'testament'=>'OT', 'slug'=>'salmos'],
        'PRO' => ['order'=>20, 'name_es'=>'Proverbios',               'name_en'=>'Proverbs',         'chapters'=>31,  'testament'=>'OT', 'slug'=>'proverbios'],
        'ECC' => ['order'=>21, 'name_es'=>'Eclesiastés',              'name_en'=>'Ecclesiastes',     'chapters'=>12,  'testament'=>'OT', 'slug'=>'eclesiastes'],
        'SNG' => ['order'=>22, 'name_es'=>'Cantar de los Cantares',   'name_en'=>'Song of Songs',    'chapters'=>8,   'testament'=>'OT', 'slug'=>'cantar-de-los-cantares'],
        'ISA' => ['order'=>23, 'name_es'=>'Isaías',                   'name_en'=>'Isaiah',           'chapters'=>66,  'testament'=>'OT', 'slug'=>'isaias'],
        'JER' => ['order'=>24, 'name_es'=>'Jeremías',                 'name_en'=>'Jeremiah',         'chapters'=>52,  'testament'=>'OT', 'slug'=>'jeremias'],
        'LAM' => ['order'=>25, 'name_es'=>'Lamentaciones',            'name_en'=>'Lamentations',     'chapters'=>5,   'testament'=>'OT', 'slug'=>'lamentaciones'],
        'EZK' => ['order'=>26, 'name_es'=>'Ezequiel',                 'name_en'=>'Ezekiel',          'chapters'=>48,  'testament'=>'OT', 'slug'=>'ezequiel'],
        'DAN' => ['order'=>27, 'name_es'=>'Daniel',                   'name_en'=>'Daniel',           'chapters'=>12,  'testament'=>'OT', 'slug'=>'daniel'],
        'HOS' => ['order'=>28, 'name_es'=>'Oseas',                    'name_en'=>'Hosea',            'chapters'=>14,  'testament'=>'OT', 'slug'=>'oseas'],
        'JOL' => ['order'=>29, 'name_es'=>'Joel',                     'name_en'=>'Joel',             'chapters'=>3,   'testament'=>'OT', 'slug'=>'joel'],
        'AMO' => ['order'=>30, 'name_es'=>'Amós',                     'name_en'=>'Amos',             'chapters'=>9,   'testament'=>'OT', 'slug'=>'amos'],
        'OBA' => ['order'=>31, 'name_es'=>'Abdías',                   'name_en'=>'Obadiah',          'chapters'=>1,   'testament'=>'OT', 'slug'=>'abdias'],
        'JON' => ['order'=>32, 'name_es'=>'Jonás',                    'name_en'=>'Jonah',            'chapters'=>4,   'testament'=>'OT', 'slug'=>'jonas'],
        'MIC' => ['order'=>33, 'name_es'=>'Miqueas',                  'name_en'=>'Micah',            'chapters'=>7,   'testament'=>'OT', 'slug'=>'miqueas'],
        'NAM' => ['order'=>34, 'name_es'=>'Nahum',                    'name_en'=>'Nahum',            'chapters'=>3,   'testament'=>'OT', 'slug'=>'nahum'],
        'HAB' => ['order'=>35, 'name_es'=>'Habacuc',                  'name_en'=>'Habakkuk',         'chapters'=>3,   'testament'=>'OT', 'slug'=>'habacuc'],
        'ZEP' => ['order'=>36, 'name_es'=>'Sofonías',                 'name_en'=>'Zephaniah',        'chapters'=>3,   'testament'=>'OT', 'slug'=>'sofonias'],
        'HAG' => ['order'=>37, 'name_es'=>'Hageo',                    'name_en'=>'Haggai',           'chapters'=>2,   'testament'=>'OT', 'slug'=>'hageo'],
        'ZEC' => ['order'=>38, 'name_es'=>'Zacarías',                 'name_en'=>'Zechariah',        'chapters'=>14,  'testament'=>'OT', 'slug'=>'zacarias'],
        'MAL' => ['order'=>39, 'name_es'=>'Malaquías',                'name_en'=>'Malachi',          'chapters'=>4,   'testament'=>'OT', 'slug'=>'malaquias'],
        'MAT' => ['order'=>40, 'name_es'=>'Mateo',                    'name_en'=>'Matthew',          'chapters'=>28,  'testament'=>'NT', 'slug'=>'mateo'],
        'MRK' => ['order'=>41, 'name_es'=>'Marcos',                   'name_en'=>'Mark',             'chapters'=>16,  'testament'=>'NT', 'slug'=>'marcos'],
        'LUK' => ['order'=>42, 'name_es'=>'Lucas',                    'name_en'=>'Luke',             'chapters'=>24,  'testament'=>'NT', 'slug'=>'lucas'],
        'JHN' => ['order'=>43, 'name_es'=>'Juan',                     'name_en'=>'John',             'chapters'=>21,  'testament'=>'NT', 'slug'=>'juan'],
        'ACT' => ['order'=>44, 'name_es'=>'Hechos',                   'name_en'=>'Acts',             'chapters'=>28,  'testament'=>'NT', 'slug'=>'hechos'],
        'ROM' => ['order'=>45, 'name_es'=>'Romanos',                  'name_en'=>'Romans',           'chapters'=>16,  'testament'=>'NT', 'slug'=>'romanos'],
        '1CO' => ['order'=>46, 'name_es'=>'1 Corintios',              'name_en'=>'1 Corinthians',    'chapters'=>16,  'testament'=>'NT', 'slug'=>'1-corintios'],
        '2CO' => ['order'=>47, 'name_es'=>'2 Corintios',              'name_en'=>'2 Corinthians',    'chapters'=>13,  'testament'=>'NT', 'slug'=>'2-corintios'],
        'GAL' => ['order'=>48, 'name_es'=>'Gálatas',                  'name_en'=>'Galatians',        'chapters'=>6,   'testament'=>'NT', 'slug'=>'galatas'],
        'EPH' => ['order'=>49, 'name_es'=>'Efesios',                  'name_en'=>'Ephesians',        'chapters'=>6,   'testament'=>'NT', 'slug'=>'efesios'],
        'PHP' => ['order'=>50, 'name_es'=>'Filipenses',               'name_en'=>'Philippians',      'chapters'=>4,   'testament'=>'NT', 'slug'=>'filipenses'],
        'COL' => ['order'=>51, 'name_es'=>'Colosenses',               'name_en'=>'Colossians',       'chapters'=>4,   'testament'=>'NT', 'slug'=>'colosenses'],
        '1TH' => ['order'=>52, 'name_es'=>'1 Tesalonicenses',         'name_en'=>'1 Thessalonians',  'chapters'=>5,   'testament'=>'NT', 'slug'=>'1-tesalonicenses'],
        '2TH' => ['order'=>53, 'name_es'=>'2 Tesalonicenses',         'name_en'=>'2 Thessalonians',  'chapters'=>3,   'testament'=>'NT', 'slug'=>'2-tesalonicenses'],
        '1TI' => ['order'=>54, 'name_es'=>'1 Timoteo',                'name_en'=>'1 Timothy',        'chapters'=>6,   'testament'=>'NT', 'slug'=>'1-timoteo'],
        '2TI' => ['order'=>55, 'name_es'=>'2 Timoteo',                'name_en'=>'2 Timothy',        'chapters'=>4,   'testament'=>'NT', 'slug'=>'2-timoteo'],
        'TIT' => ['order'=>56, 'name_es'=>'Tito',                     'name_en'=>'Titus',            'chapters'=>3,   'testament'=>'NT', 'slug'=>'tito'],
        'PHM' => ['order'=>57, 'name_es'=>'Filemón',                  'name_en'=>'Philemon',         'chapters'=>1,   'testament'=>'NT', 'slug'=>'filemon'],
        'HEB' => ['order'=>58, 'name_es'=>'Hebreos',                  'name_en'=>'Hebrews',          'chapters'=>13,  'testament'=>'NT', 'slug'=>'hebreos'],
        'JAS' => ['order'=>59, 'name_es'=>'Santiago',                 'name_en'=>'James',            'chapters'=>5,   'testament'=>'NT', 'slug'=>'santiago'],
        '1PE' => ['order'=>60, 'name_es'=>'1 Pedro',                  'name_en'=>'1 Peter',          'chapters'=>5,   'testament'=>'NT', 'slug'=>'1-pedro'],
        '2PE' => ['order'=>61, 'name_es'=>'2 Pedro',                  'name_en'=>'2 Peter',          'chapters'=>3,   'testament'=>'NT', 'slug'=>'2-pedro'],
        '1JN' => ['order'=>62, 'name_es'=>'1 Juan',                   'name_en'=>'1 John',           'chapters'=>5,   'testament'=>'NT', 'slug'=>'1-juan'],
        '2JN' => ['order'=>63, 'name_es'=>'2 Juan',                   'name_en'=>'2 John',           'chapters'=>1,   'testament'=>'NT', 'slug'=>'2-juan'],
        '3JN' => ['order'=>64, 'name_es'=>'3 Juan',                   'name_en'=>'3 John',           'chapters'=>1,   'testament'=>'NT', 'slug'=>'3-juan'],
        'JUD' => ['order'=>65, 'name_es'=>'Judas',                    'name_en'=>'Jude',             'chapters'=>1,   'testament'=>'NT', 'slug'=>'judas'],
        'REV' => ['order'=>66, 'name_es'=>'Apocalipsis',              'name_en'=>'Revelation',       'chapters'=>22,  'testament'=>'NT', 'slug'=>'apocalipsis'],
    ];

    // English book name → USFX code (for resolving reading_block.book)
    private const ENGLISH_TO_CODE = [
        'genesis'=>'GEN','exodus'=>'EXO','leviticus'=>'LEV','numbers'=>'NUM','deuteronomy'=>'DEU',
        'joshua'=>'JOS','judges'=>'JDG','ruth'=>'RUT','1 samuel'=>'1SA','2 samuel'=>'2SA',
        '1 kings'=>'1KI','2 kings'=>'2KI','1 chronicles'=>'1CH','2 chronicles'=>'2CH',
        'ezra'=>'EZR','nehemiah'=>'NEH','esther'=>'EST','job'=>'JOB',
        'psalms'=>'PSA','psalm'=>'PSA','proverbs'=>'PRO','ecclesiastes'=>'ECC',
        'song of songs'=>'SNG','song of solomon'=>'SNG','isaiah'=>'ISA','jeremiah'=>'JER',
        'lamentations'=>'LAM','ezekiel'=>'EZK','daniel'=>'DAN','hosea'=>'HOS',
        'joel'=>'JOL','amos'=>'AMO','obadiah'=>'OBA','jonah'=>'JON','micah'=>'MIC',
        'nahum'=>'NAM','habakkuk'=>'HAB','zephaniah'=>'ZEP','haggai'=>'HAG',
        'zechariah'=>'ZEC','malachi'=>'MAL','matthew'=>'MAT','mark'=>'MRK',
        'luke'=>'LUK','john'=>'JHN','acts'=>'ACT','romans'=>'ROM',
        '1 corinthians'=>'1CO','2 corinthians'=>'2CO','galatians'=>'GAL',
        'ephesians'=>'EPH','philippians'=>'PHP','colossians'=>'COL',
        '1 thessalonians'=>'1TH','2 thessalonians'=>'2TH','1 timothy'=>'1TI',
        '2 timothy'=>'2TI','titus'=>'TIT','philemon'=>'PHM','hebrews'=>'HEB',
        'james'=>'JAS','1 peter'=>'1PE','2 peter'=>'2PE','1 john'=>'1JN',
        '2 john'=>'2JN','3 john'=>'3JN','jude'=>'JUD','revelation'=>'REV',
    ];

    private bool $dryRun = false;

    /** @var array<string,int> bookCode→DB id */
    private array $bookIdCache = [];

    /** @var array<string,array<int,int>> bookCode→chapterNum→DB id */
    private array $chapterIdCache = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $sourcePath = $this->resolveSourcePath();
        if (! $sourcePath) {
            return self::FAILURE;
        }

        $hash = hash_file('sha256', $sourcePath);
        $this->info("Fuente : {$sourcePath}");
        $this->info("SHA-256 : {$hash}");
        $this->info('Tamaño : ' . number_format(filesize($sourcePath)) . ' bytes');

        // Copy to versioned storage location if not already there
        $storagePath = storage_path('app/imports/spaRV1909_usfx.xml');
        if (realpath($sourcePath) !== realpath($storagePath)) {
            $this->info('Copiando a storage/app/imports/...');
            if (! $this->dryRun) {
                if (! is_dir(dirname($storagePath))) {
                    mkdir(dirname($storagePath), 0755, true);
                }
                copy($sourcePath, $storagePath);
            }
        }

        $translationCode = $this->option('translation');
        $translation = $this->upsertTranslation($translationCode, $hash, $storagePath);

        $this->info('');
        $this->info('── Fase 1: Upsert 66 libros bíblicos ──');
        $this->upsertAllBooks();

        $this->info('');
        $this->info('── Fase 2: Parse USFX y guardar versículos ──');
        $stats = $this->parseAndImport($sourcePath, $translation->id);

        $this->info('');
        $this->info('── Fase 3: Actualizar verse_count en capítulos ──');
        if (! $this->dryRun) {
            $this->updateVerseCounts();
        }

        if (! $this->option('skip-resolve')) {
            $this->info('');
            $this->info('── Fase 4: Resolver rangos en reading_blocks ──');
            $resolved = $this->resolveReadingBlockRanges();
            $this->info("   Resueltos: {$resolved['ok']} | Sin libros: {$resolved['missing_book']} | Errores: {$resolved['error']}");
        }

        $this->printReport($stats, $hash, $storagePath);

        return self::SUCCESS;
    }

    private function resolveSourcePath(): ?string
    {
        $opt = $this->option('source');
        if ($opt) {
            $path = $opt;
        } else {
            // Default: try the known download location
            $candidates = [
                storage_path('app/imports/spaRV1909_usfx.xml'),
                'C:/Users/garci/Downloads/spaRV1909_usfx/spaRV1909_usfx.xml',
            ];
            $path = collect($candidates)->first(fn($p) => file_exists($p));
        }

        if (! $path || ! file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");
            $this->line('Usa --source=/ruta/al/spaRV1909_usfx.xml');
            return null;
        }

        return realpath($path);
    }

    private function upsertTranslation(string $code, string $hash, string $storagePath): Translation
    {
        $data = [
            'name'                 => 'Reina-Valera Antigua (1909)',
            'language'             => 'es',
            'is_public_domain'     => true,
            'license_status'       => 'none',
            'can_display_full_text'=> true,
            'attribution'          => 'eBible.org — Public Domain / Dominio Público',
            'license_label'        => 'Public Domain',
            'source_url'           => 'https://ebible.org/find/details.php?id=spaRV1909',
            'source_file_hash'     => $hash,
            'imported_at'          => now(),
            'sort_order'           => 1,
        ];

        if (! $this->dryRun) {
            Translation::updateOrCreate(['code' => $code], $data);
        }

        $t = Translation::where('code', $code)->first();
        $this->info("Traducción: {$code} — {$data['name']}");
        return $t ?? new Translation(array_merge(['id' => 0, 'code' => $code], $data));
    }

    private function upsertAllBooks(): void
    {
        $bar = $this->output->createProgressBar(count(self::BOOK_CATALOG));
        $bar->start();

        foreach (self::BOOK_CATALOG as $code => $info) {
            if (! $this->dryRun) {
                $book = BiblicalBook::updateOrCreate(
                    ['slug' => $info['slug']],
                    [
                        'osis_code'       => $code,
                        'name_es'         => $info['name_es'],
                        'name_en'         => $info['name_en'],
                        'chapter_count'   => $info['chapters'],
                        'testament'       => $info['testament'],
                        'canonical_order' => $info['order'],
                    ]
                );
                $this->bookIdCache[$code] = $book->id;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('   66 libros creados/actualizados.');
    }

    private function parseAndImport(string $filePath, int $translationId): array
    {
        $stats = [
            'books'   => 0,
            'chapters'=> 0,
            'verses'  => 0,
            'skipped' => 0,
        ];

        // Pre-load book IDs if not cached yet
        if (empty($this->bookIdCache) && ! $this->dryRun) {
            $this->bookIdCache = BiblicalBook::whereNotNull('osis_code')
                ->pluck('id', 'osis_code')
                ->toArray();
        }

        $reader = new \XMLReader();
        if (! $reader->open($filePath)) {
            $this->error('No se pudo abrir el archivo USFX.');
            return $stats;
        }

        $currentBookCode = null;
        $currentBookId   = null;
        $currentChNum    = null;
        $currentChId     = null;
        $currentVerseNum = null;
        $inVerse         = false;
        $verseText       = '';

        // Batch insert buffer
        $verseBatch = [];
        $batchSize  = 500;

        $this->getOutput()->write('   ');
        $bar = $this->output->createProgressBar(self::EXPECTED_CHAPTERS);
        $bar->setFormat(' %current%/%max% cap [%bar%] %percent:3s%%');
        $bar->start();

        while ($reader->read()) {
            switch ($reader->nodeType) {
                case \XMLReader::ELEMENT:
                    switch ($reader->localName) {
                        case 'book':
                            $bookCode = strtoupper(trim($reader->getAttribute('id') ?? ''));
                            if (! isset(self::BOOK_CATALOG[$bookCode])) {
                                $currentBookCode = null;
                                $currentBookId   = null;
                                break;
                            }
                            $currentBookCode = $bookCode;
                            $currentBookId   = $this->bookIdCache[$bookCode] ?? null;
                            $currentChNum    = null;
                            $currentChId     = null;
                            $inVerse         = false;
                            $stats['books']++;
                            break;

                        case 'c':
                            if ($currentBookCode === null) break;
                            $inVerse     = false;
                            $currentChNum = (int) ($reader->getAttribute('id') ?? 0);
                            if ($currentChNum < 1) break;

                            if (! $this->dryRun && $currentBookId) {
                                $ch = BibleChapter::firstOrCreate(
                                    ['biblical_book_id' => $currentBookId, 'chapter_number' => $currentChNum],
                                    ['verse_count' => 0]
                                );
                                $currentChId = $ch->id;
                                $this->chapterIdCache[$currentBookCode][$currentChNum] = $ch->id;
                            } else {
                                $currentChId = null;
                            }
                            $stats['chapters']++;
                            $bar->advance();
                            break;

                        case 'v':
                            if ($currentChId === null && ! $this->dryRun) break;
                            if ($currentChNum === null) break;
                            $vNum = (int) ($reader->getAttribute('id') ?? 0);
                            if ($vNum < 1) break;
                            $currentVerseNum = $vNum;
                            $inVerse         = true;
                            $verseText       = '';
                            break;

                        case 've':
                            // Verse end — save accumulated text
                            if ($inVerse && $currentVerseNum !== null && $currentChId !== null) {
                                $clean = $this->cleanVerseText($verseText);
                                if ($clean !== '') {
                                    $verseBatch[] = [
                                        'chapter_id'     => $currentChId,
                                        'verse_number'   => $currentVerseNum,
                                        'translation_id' => $translationId,
                                        'text'           => $clean,
                                        'created_at'     => now(),
                                        'updated_at'     => now(),
                                    ];
                                    $stats['verses']++;

                                    if (! $this->dryRun && count($verseBatch) >= $batchSize) {
                                        $this->flushVerseBatch($verseBatch);
                                        $verseBatch = [];
                                    }
                                }
                            }
                            $inVerse     = false;
                            $verseText   = '';
                            $currentVerseNum = null;
                            break;
                    }
                    break;

                case \XMLReader::TEXT:
                case \XMLReader::CDATA:
                case \XMLReader::WHITESPACE:
                case \XMLReader::SIGNIFICANT_WHITESPACE:
                    if ($inVerse) {
                        $verseText .= $reader->value;
                    }
                    break;
            }
        }

        $reader->close();

        // Flush remaining batch
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

    private function cleanVerseText(string $raw): string
    {
        // Normalize whitespace: collapse runs, trim
        $text = preg_replace('/\s+/', ' ', $raw);
        return trim($text ?? '');
    }

    private function updateVerseCounts(): void
    {
        DB::statement('
            UPDATE bible_chapters bc
            SET verse_count = (
                SELECT COUNT(DISTINCT bv.verse_number)
                FROM bible_verses bv
                WHERE bv.chapter_id = bc.id
            )
        ');
        $this->info('   verse_count actualizado en todos los capítulos.');
    }

    private function resolveReadingBlockRanges(): array
    {
        $stats = ['ok' => 0, 'missing_book' => 0, 'error' => 0];

        // Build book lookup: English name (lowercase) → book id
        $bookMap = BiblicalBook::whereNotNull('osis_code')
            ->get()
            ->keyBy(fn($b) => strtolower($b->name_en));

        // Also index by USFX code
        $bookByCode = BiblicalBook::whereNotNull('osis_code')
            ->get()
            ->keyBy('osis_code');

        $blocks = ReadingBlock::all();
        $this->getOutput()->progressStart($blocks->count());

        foreach ($blocks as $block) {
            try {
                $bookName = strtolower(trim($block->book ?? ''));
                $code     = self::ENGLISH_TO_CODE[$bookName] ?? null;
                $bookObj  = $code ? ($bookByCode[$code] ?? null) : ($bookMap[$bookName] ?? null);

                if (! $bookObj) {
                    // Try partial match with multi-word prefix stripping
                    foreach (self::ENGLISH_TO_CODE as $en => $c) {
                        if (str_contains($bookName, $en)) {
                            $bookObj = $bookByCode[$c] ?? null;
                            break;
                        }
                    }
                }

                if (! $bookObj) {
                    $stats['missing_book']++;
                    $this->getOutput()->progressAdvance();
                    continue;
                }

                [$startCh, $startV, $endCh, $endV] = $this->parsePassageRange(
                    $block->passage_start ?? '',
                    $block->book ?? ''
                );

                $block->start_book_id  = $bookObj->id;
                $block->start_chapter  = $startCh;
                $block->start_verse    = $startV;
                $block->end_book_id    = $bookObj->id;
                $block->end_chapter    = $endCh;
                $block->end_verse      = $endV;
                $block->save();

                $stats['ok']++;
            } catch (\Throwable $e) {
                $stats['error']++;
            }

            $this->getOutput()->progressAdvance();
        }

        $this->getOutput()->progressFinish();
        return $stats;
    }

    /**
     * Parse passage_start string into [startChapter, startVerse|null, endChapter, endVerse|null].
     *
     * Formats:
     *  "1-3"       → chapters 1–3, no verse restriction   → [1, null, 3, null]
     *  "11:1-9"    → chapter 11, verses 1–9               → [11, 1, 11, 9]
     *  "22:1"      → chapter 22, verse 1                  → [22, 1, 22, 1]
     *  "16"        → chapter 16, no verse restriction     → [16, null, 16, null]
     *  "2 Kings 8:1-15"  → strip book prefix then parse   → [8, 1, 8, 15]
     */
    private function parsePassageRange(string $raw, string $bookName): array
    {
        // Strip repeated book name prefix (buggy entries)
        $cleaned = trim($raw);
        $bookLower = strtolower(trim($bookName));
        if (str_starts_with(strtolower($cleaned), $bookLower)) {
            $cleaned = trim(substr($cleaned, strlen($bookLower)));
        }

        if ($cleaned === '') {
            return [null, null, null, null];
        }

        // Format: "chapter:verseStart-verseEnd" or "chapter:verse"
        if (str_contains($cleaned, ':')) {
            [$chPart, $versePart] = explode(':', $cleaned, 2);
            $ch = (int) trim($chPart);
            if (str_contains($versePart, '-')) {
                [$vs, $ve] = explode('-', $versePart, 2);
                return [$ch, (int) trim($vs), $ch, (int) trim($ve)];
            }
            $v = (int) trim($versePart);
            return [$ch, $v, $ch, $v];
        }

        // Format: "startChapter-endChapter" (chapter range)
        if (str_contains($cleaned, '-')) {
            [$sc, $ec] = explode('-', $cleaned, 2);
            return [(int) trim($sc), null, (int) trim($ec), null];
        }

        // Single chapter
        $ch = (int) $cleaned;
        return [$ch, null, $ch, null];
    }

    private function printReport(array $stats, string $hash, string $storagePath): void
    {
        $this->newLine();
        $this->line('════════════════════════════════════════════');
        $this->info('   REPORTE DE IMPORTACIÓN — RVA1909');
        $this->line('════════════════════════════════════════════');

        // Live counts from DB
        $dbBooks    = BiblicalBook::whereNotNull('osis_code')->count();
        $dbChapters = DB::table('bible_chapters')->count();
        $dbVerses   = DB::table('bible_verses')->whereIn(
            'translation_id',
            Translation::where('code', $this->option('translation'))->pluck('id')
        )->count();

        $booksOk    = $dbBooks    === self::EXPECTED_BOOKS    ? '✅' : '❌';
        $chaptersOk = $dbChapters === self::EXPECTED_CHAPTERS ? '✅' : '❌';

        $this->line("  Libros importados     : {$booksOk} {$dbBooks} / " . self::EXPECTED_BOOKS);
        $this->line("  Capítulos importados  : {$chaptersOk} {$dbChapters} / " . self::EXPECTED_CHAPTERS);
        $this->line("  Versículos totales    : {$dbVerses}");
        $this->line("  SHA-256 fuente        : {$hash}");
        $this->line("  Archivo versionado    : {$storagePath}");
        $this->line("  Licencia              : Public Domain / Dominio Público");
        $this->line("  Fuente                : https://ebible.org/find/details.php?id=spaRV1909");
        $this->newLine();

        if ($dbBooks !== self::EXPECTED_BOOKS) {
            $this->error("  ⚠ Se esperaban 66 libros, se encontraron {$dbBooks}.");
        }
        if ($dbChapters !== self::EXPECTED_CHAPTERS) {
            $this->error("  ⚠ Se esperaban 1,189 capítulos, se encontraron {$dbChapters}.");
        }

        // Spot check: Genesis 1:1
        $this->line('  ── Spot checks ──');
        $this->spotCheck('GEN', 1, 1, $this->option('translation'));
        $this->spotCheck('1SA', 16, 1, $this->option('translation'));
        $this->spotCheck('JHN', 3, 16, $this->option('translation'));
        $this->newLine();
    }

    private function spotCheck(string $code, int $chapter, int $verse, string $translationCode): void
    {
        $book = BiblicalBook::where('osis_code', $code)->first();
        if (! $book) { $this->line("  [CHECK] {$code} — libro no encontrado"); return; }

        $ch = BibleChapter::where('biblical_book_id', $book->id)
            ->where('chapter_number', $chapter)->first();
        if (! $ch) { $this->line("  [CHECK] {$code} {$chapter} — capítulo no encontrado"); return; }

        $t = Translation::where('code', $translationCode)->first();
        if (! $t) { $this->line("  [CHECK] traducción {$translationCode} no encontrada"); return; }

        $v = BibleVerse::where('chapter_id', $ch->id)
            ->where('verse_number', $verse)
            ->where('translation_id', $t->id)
            ->first();

        if (! $v) {
            $this->line("  [CHECK] ❌ {$book->name_es} {$chapter}:{$verse} — versículo no encontrado");
        } else {
            $preview = Str::limit($v->text, 60);
            $this->line("  [CHECK] ✅ {$book->name_es} {$chapter}:{$verse} → \"{$preview}\"");
        }
    }
}
