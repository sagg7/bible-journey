<?php

namespace App\Console\Commands;

use App\Models\ChronologicalReadingSet;
use App\Models\CrsSpiritOfProphecyContent;
use App\Services\Egw\EgwWritingsClient;
use App\Support\EgwVolumeMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BuildCrsSpiritOfProphecyContent extends Command
{
    protected $signature = 'crs:build-spirit-of-prophecy-content
        {--force : Replace existing generated content}
        {--limit= : Only process the first N CRS (for testing)}
        {--locale= : Only build for one locale (es or en)}';

    protected $description = 'Fetch cited Spirit of Prophecy (EGW Conflict of the Ages series) excerpts per CRS, in Spanish and English';

    private const CHAPTER_BASIS_PATTERN = '/^(this chapter is based on|este cap[ií]tulo est[aá] basado en)/iu';

    public function handle(EgwWritingsClient $egw): int
    {
        if (! $egw->configured()) {
            $this->error('EGW_CLIENT_ID / EGW_CLIENT_SECRET no configurados en .env');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $locales = $this->option('locale') ? [$this->option('locale')] : ['es', 'en'];

        $query = ChronologicalReadingSet::with(['blocks.startBook', 'spiritOfProphecyContents']);
        if ($limit) {
            $query->limit($limit);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $empty = 0;

        foreach ($query->cursor() as $crs) {
            $block = $crs->blocks->firstWhere('role', 'narrative_anchor') ?? $crs->blocks->first();

            if (! $block || ! $block->startBook || ! $block->start_chapter) {
                $skipped++;

                continue;
            }

            foreach ($locales as $locale) {
                $existing = $crs->spiritOfProphecyContents->firstWhere('locale', $locale);
                if ($existing && ! $force) {
                    $skipped++;

                    continue;
                }

                $volume = EgwVolumeMap::volumeFor($block->startBook->canonical_order, $locale);
                if (! $volume) {
                    $skipped++;

                    continue;
                }

                $bookName = $locale === 'en' ? $block->startBook->name_en : $block->startBook->name_es;
                $searchQuery = "{$bookName} {$block->start_chapter}";

                try {
                    $results = $egw->searchBook($locale, $volume['pubnr'], $searchQuery, 5);
                } catch (Throwable $e) {
                    $this->warn("  [{$crs->source_map}/{$locale}] error: ".$e->getMessage());

                    continue;
                }

                $excerpts = $this->selectExcerpts($results);

                if (empty($excerpts)) {
                    $empty++;
                }

                $record = CrsSpiritOfProphecyContent::updateOrCreate(
                    ['crs_id' => $crs->id, 'locale' => $locale],
                    [
                        'source_book_code' => $volume['code'],
                        'source_book_title' => $volume['title'],
                        'excerpts' => $excerpts,
                        'content_version' => 'egw-v1',
                    ]
                );

                $record->wasRecentlyCreated ? $created++ : $updated++;

                // Be considerate of the external API — no documented rate limit,
                // so throttle lightly across ~1080 calls (540 CRS x 2 locales).
                usleep(120_000);
            }
        }

        $this->info("Listo. Created={$created}, updated={$updated}, skipped={$skipped}, sin_resultados={$empty}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array{snippet:string, refcode_short:string, refcode_long:string, para_id:string}>
     */
    private function selectExcerpts(array $results): array
    {
        $excerpts = [];

        foreach ($results as $result) {
            $plain = trim(strip_tags($result['snippet'] ?? ''));

            // Skip the bare "This chapter is based on X" chapter-heading
            // annotation — it has no real narrative content to show.
            if (preg_match(self::CHAPTER_BASIS_PATTERN, $plain) === 1) {
                continue;
            }

            if ($plain === '') {
                continue;
            }

            $excerpts[] = [
                'snippet' => $plain,
                'refcode_short' => $result['refcode_short'] ?? '',
                'refcode_long' => $result['refcode_long'] ?? '',
                'para_id' => $result['para_id'] ?? '',
            ];

            if (count($excerpts) >= 3) {
                break;
            }
        }

        return $excerpts;
    }
}
