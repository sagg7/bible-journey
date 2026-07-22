<?php

namespace App\Console\Commands;

use App\Models\ChronologicalReadingSet;
use Illuminate\Console\Command;

class BackfillCrsEstimatedDates extends Command
{
    protected $signature = 'crs:backfill-estimated-dates
        {--dry-run : Show what would be updated without writing}
        {--force : Replace existing estimated dates}';

    protected $description = 'Backfill conservative estimated date labels for chronological reading sets';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $stats = [
            'updated' => 0,
            'skipped_existing' => 0,
            'missing_rule' => 0,
            'alta' => 0,
            'probable' => 0,
            'debatida' => 0,
            'especulativa' => 0,
        ];

        ChronologicalReadingSet::query()
            ->orderBy('id')
            ->each(function (ChronologicalReadingSet $crs) use ($dryRun, $force, &$stats): void {
                if (! $force && filled($crs->approximate_date_start)) {
                    $stats['skipped_existing']++;

                    return;
                }

                $estimate = $this->estimate($crs);

                if ($estimate === null) {
                    $stats['missing_rule']++;
                    $this->warn("No date rule for {$crs->source_map} ({$crs->era_slug}) {$crs->title_es}");

                    return;
                }

                $stats['updated']++;
                $stats[$estimate['confidence']]++;

                if ($dryRun) {
                    $this->line("{$crs->source_map}: {$estimate['start']} [{$estimate['confidence']}]");

                    return;
                }

                $crs->forceFill([
                    'approximate_date_start' => $estimate['start'],
                    'approximate_date_end' => $estimate['end'] ?? null,
                    'date_confidence' => $estimate['confidence'],
                    'approximate_year_start' => $estimate['year_start'],
                    'approximate_year_end' => $estimate['year_end'],
                ])->save();
            });

        $this->newLine();
        $this->info('CRS estimated date backfill');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all()
        );

        if ($dryRun) {
            $this->warn('Dry run only. No records were changed.');
        }

        return $stats['missing_rule'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function estimate(ChronologicalReadingSet $crs): ?array
    {
        $source = $crs->source_map;
        $slug = $crs->era_slug;

        return $this->sourceMapEstimate($source)
            ?? $this->gospelEstimate($source)
            ?? $this->actsAndLettersEstimate($source)
            ?? $this->eraEstimate($slug)
            ?? $this->fallbackEstimate($crs);
    }

    private function sourceMapEstimate(string $source): ?array
    {
        $exact = [
            // Primeval and patriarchal material
            'CRS-GEN-019' => ['tiempo primordial; no fechable', 'especulativa'],
            'CRS-GEN-020' => ['tiempo primordial; no fechable', 'especulativa'],
            'CRS-GEN-021' => ['tiempo primordial; no fechable', 'especulativa'],
            'CRS-GEN-022' => ['tiempo primordial; no fechable', 'especulativa'],
            'CRS-GEN-023' => ['c. 2100-2000 a.C.', 'debatida'],
            'CRS-GEN-024' => ['c. 2100-2000 a.C.', 'debatida'],
            'CRS-GEN-025' => ['c. 2000-1900 a.C.', 'debatida'],
            'CRS-GEN-026' => ['c. 1900-1800 a.C.', 'debatida'],
            'CRS-GEN-016' => ['c. 1900-1800 a.C.', 'debatida'],
            'CRS-GEN-017' => ['c. 1900-1800 a.C.', 'debatida'],
            'CRS-GEN-018' => ['c. 1800 a.C.', 'debatida'],

            // Exodus through settlement, using the early-date framework already implicit in the route.
            'CRS-EXO-001' => ['c. 1526-1446 a.C.', 'debatida'],
            'CRS-EXO-002' => ['c. 1446 a.C.', 'debatida'],
            'CRS-EXO-003' => ['c. 1446 a.C.', 'debatida'],
            'CRS-EXO-004' => ['c. 1446 a.C.', 'debatida'],
            'CRS-EXO-005' => ['c. 1446 a.C.', 'debatida'],
            'CRS-EXO-006' => ['c. 1446 a.C.', 'debatida'],
            'CRS-EXO-007' => ['c. 1446 a.C.', 'debatida'],
            'CRS-EXO-008' => ['c. 1446-1445 a.C.', 'debatida'],
            'CRS-EXO-009' => ['c. 1446-1445 a.C.', 'debatida'],
            'CRS-EXO-010' => ['c. 1446-1445 a.C.', 'debatida'],
            'CRS-EXO-011' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-001' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-002' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-003' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-004' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-005' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-006' => ['c. 1445 a.C.', 'debatida'],
            'CRS-LEV-007' => ['c. 1445 a.C.', 'debatida'],
            'CRS-DEU-001' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-002' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-003' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-004' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-005' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-006' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-007' => ['c. 1406 a.C.', 'debatida'],
            'CRS-DEU-008' => ['c. 1406 a.C.', 'debatida'],

            // Samuel, David, Solomon
            'CRS-1SA-001' => ['c. 1100-1050 a.C.', 'probable'],
            'CRS-1SA-002' => ['c. 1070-1050 a.C.', 'probable'],
            'CRS-1SA-003' => ['c. 1050 a.C.', 'probable'],
            'CRS-1SA-004' => ['c. 1049 a.C.', 'probable'],
            'CRS-1SA-005' => ['c. 1048-1025 a.C.', 'probable'],
            'CRS-1SA-006' => ['c. 1025 a.C.', 'probable'],
            'CRS-1SA-007' => ['c. 1020 a.C.', 'alta'],
            'CRS-1SA-008' => ['c. 1020-1018 a.C.', 'probable'],
            'CRS-1SA-009' => ['c. 1016 a.C.', 'probable'],
            'CRS-1SA-010' => ['c. 1018-1016 a.C.', 'probable'],
            'CRS-1SA-011' => ['c. 1016 a.C.', 'probable'],
            'CRS-1SA-012' => ['c. 1015 a.C.', 'probable'],
            'CRS-1SA-013' => ['c. 1015-1014 a.C.', 'probable'],
            'CRS-1SA-014' => ['c. 1014 a.C.', 'probable'],
            'CRS-1SA-015' => ['c. 1013 a.C.', 'probable'],
            'CRS-1SA-016' => ['c. 1013 a.C.', 'probable'],
            'CRS-1SA-017' => ['c. 1012 a.C.', 'debatida'],
            'CRS-1SA-018' => ['c. 1011 a.C.', 'probable'],
            'CRS-1SA-019' => ['c. 1011 a.C.', 'probable'],
            'CRS-1SA-020' => ['c. 1011 a.C.', 'probable'],
            'CRS-1SA-021' => ['c. 1010 a.C.', 'alta'],
            'CRS-1CH-007' => ['c. 1010 a.C.', 'alta'],

            'CRS-2SA-001' => ['c. 1010 a.C.', 'alta'],
            'CRS-2SA-002' => ['c. 1008-1003 a.C.', 'probable'],
            'CRS-2SA-003' => ['c. 1003 a.C.', 'alta'],
            'CRS-2SA-004' => ['c. 1000 a.C.', 'probable'],
            'CRS-2SA-005' => ['c. 1000 a.C.', 'probable'],
            'CRS-2SA-006' => ['c. 995 a.C.', 'probable'],
            'CRS-2SA-007' => ['c. 995-990 a.C.', 'probable'],
            'CRS-2SA-008' => ['c. 990 a.C.', 'probable'],
            'CRS-2SA-009' => ['c. 990 a.C.', 'probable'],
            'CRS-2SA-010' => ['c. 989 a.C.', 'probable'],
            'CRS-2SA-011' => ['c. 985 a.C.', 'probable'],
            'CRS-2SA-012' => ['c. 982 a.C.', 'probable'],
            'CRS-2SA-013' => ['c. 980 a.C.', 'probable'],
            'CRS-2SA-014' => ['c. 980 a.C.', 'probable'],
            'CRS-2SA-015' => ['c. 980 a.C.', 'probable'],
            'CRS-2SA-016' => ['c. 980 a.C.', 'probable'],
            'CRS-2SA-017' => ['c. 979 a.C.', 'probable'],
            'CRS-2SA-018' => ['c. 970 a.C.', 'debatida'],
            'CRS-2SA-019' => ['c. 970 a.C.', 'debatida'],
            'CRS-2SA-020' => ['c. 970 a.C.', 'debatida'],
            'CRS-2SA-021' => ['c. 970 a.C.', 'probable'],

            'CRS-1KG-001' => ['c. 970 a.C.', 'alta'],
            'CRS-1KG-002' => ['c. 970 a.C.', 'alta'],
            'CRS-1KG-003' => ['c. 970 a.C.', 'probable'],
            'CRS-1KG-004' => ['c. 970-966 a.C.', 'probable'],
            'CRS-1KG-005' => ['c. 966-959 a.C.', 'probable'],
            'CRS-1KG-006' => ['c. 966-959 a.C.', 'probable'],
            'CRS-1KG-007' => ['c. 959 a.C.', 'probable'],
            'CRS-1KG-008' => ['c. 950-931 a.C.', 'probable'],
            'CRS-1KG-009' => ['c. 950-931 a.C.', 'probable'],
            'CRS-1KG-010' => ['c. 940-931 a.C.', 'probable'],

            // Divided kingdom and Judah's fall
            'CRS-1KG-011' => ['c. 930 a.C.', 'alta'],
            'CRS-1KG-012' => ['c. 930 a.C.', 'probable'],
            'CRS-1KG-013' => ['c. 913 a.C.', 'probable'],
            'CRS-1KG-014' => ['c. 913-870 a.C.', 'probable'],
            'CRS-1KG-015' => ['c. 885-874 a.C.', 'probable'],
            'CRS-1KG-016' => ['c. 870-860 a.C.', 'probable'],
            'CRS-1KG-017' => ['c. 870-860 a.C.', 'probable'],
            'CRS-1KG-018' => ['c. 870-860 a.C.', 'probable'],
            'CRS-1KG-019' => ['c. 857-852 a.C.', 'probable'],
            'CRS-1KG-020' => ['c. 857-852 a.C.', 'probable'],
            'CRS-1KG-021' => ['c. 853 a.C.', 'probable'],
            'CRS-2K-017' => ['c. 722-721 a.C.', 'alta'],
            'CRS-2K-019' => ['c. 701 a.C.', 'alta'],
            'CRS-2K-022' => ['c. 622 a.C.', 'alta'],
            'CRS-2K-025' => ['c. 586-561 a.C.', 'alta'],

            // Prophets and exile/restoration
            'CRS-04-028' => ['c. 605 a.C.', 'alta'],
            'CRS-04-034' => ['c. 593 a.C.', 'alta'],
            'CRS-04-037' => ['c. 587 a.C.', 'alta'],
            'CRS-04-039' => ['c. 586 a.C.', 'alta'],
            'CRS-04-052' => ['sin fecha segura', 'especulativa'],
            'CRS-05-002' => ['c. 605 a.C.', 'alta'],
            'CRS-05-004' => ['c. 593 a.C.', 'alta'],
            'CRS-05-019' => ['c. 539 a.C.', 'alta'],
            'CRS-05-022' => ['c. 538 a.C.', 'alta'],
            'CRS-05-026' => ['c. 520 a.C.', 'alta'],
            'CRS-05-032' => ['c. 516 a.C.', 'alta'],
            'CRS-05-036' => ['c. 458 a.C.', 'alta'],
            'CRS-05-038' => ['c. 445 a.C.', 'alta'],

            // Psalms and wisdom collection anchors
            'CRS-PSA-002' => ['c. 980 a.C.', 'probable'],
            'CRS-PSA-006' => ['c. 970 a.C.', 'debatida'],
            'CRS-PSA-010' => ['c. 1016 a.C.', 'alta'],
            'CRS-PSA-012' => ['c. 989 a.C.', 'alta'],
            'CRS-PSA-013' => ['c. 1016 a.C.', 'alta'],
            'CRS-PSA-015' => ['c. 1015-1014 a.C.', 'probable'],
            'CRS-PSA-017' => ['c. 1012 a.C.', 'probable'],
            'CRS-PSA-018' => ['c. 1015 a.C.', 'debatida'],
            'CRS-PSA-020' => ['c. 1016 a.C.', 'alta'],
            'CRS-PSA-021' => ['c. 995 a.C.', 'probable'],
            'CRS-PSA-025' => ['c. 970-931 a.C.', 'debatida'],
            'CRS-PSA-027' => ['tradicion mosaica; fecha no verificable', 'especulativa'],
            'CRS-PSA-036' => ['c. 970-931 a.C.', 'debatida'],
            'CRS-PSA-042' => ['c. 1010-970 a.C.', 'debatida'],
        ];

        if (isset($exact[$source])) {
            return $this->make($exact[$source][0], $exact[$source][1]);
        }

        if (preg_match('/^CRS-NUM-00[1-3]$/', $source)) {
            return $this->make('c. 1445 a.C.', 'debatida');
        }

        if (preg_match('/^CRS-NUM-00[4-6]$/', $source)) {
            return $this->make('c. 1445-1407 a.C.', 'debatida');
        }

        if (preg_match('/^CRS-NUM-0(0[7-9]|1[0-2])$/', $source)) {
            return $this->make('c. 1407-1406 a.C.', 'debatida');
        }

        if (preg_match('/^CRS-JOS-00[1-9]$/', $source)) {
            return $this->make('c. 1406-1375 a.C.', 'debatida');
        }

        if (preg_match('/^CRS-JDG-00[1-8]$/', $source)) {
            return $this->make('c. 1375-1050 a.C.', 'debatida');
        }

        if ($source === 'CRS-RUT-001') {
            return $this->make('c. 1200-1050 a.C.', 'debatida');
        }

        return $this->chroniclesAndKingsEstimate($source)
            ?? $this->psalterEstimate($source)
            ?? $this->prophetsEstimate($source);
    }

    private function chroniclesAndKingsEstimate(string $source): ?array
    {
        if (preg_match('/^CRS-1CH-00[89]$/', $source)) {
            return $this->make('c. 1003 a.C.', 'alta');
        }

        if (preg_match('/^CRS-1CH-01[0-7]$/', $source)) {
            return $this->make('c. 1000-970 a.C.', 'probable');
        }

        if (preg_match('/^CRS-1CH-0(18|19|20)$/', $source)) {
            return $this->make('c. 970 a.C.', 'probable');
        }

        if (preg_match('/^CRS-BR-2CH-00[1-5]$/', $source)) {
            return $this->make('c. 970-931 a.C.', 'probable');
        }

        $secondChronicles = [
            'CRS-BR-2CH-006' => ['c. 930 a.C.', 'alta'],
            'CRS-BR-2CH-007' => ['c. 930-913 a.C.', 'probable'],
            'CRS-BR-2CH-008' => ['c. 925 a.C.', 'alta'],
            'CRS-BR-2CH-009' => ['c. 913-911 a.C.', 'probable'],
            'CRS-BR-2CH-010' => ['c. 911-870 a.C.', 'probable'],
            'CRS-BR-2CH-011' => ['c. 895-870 a.C.', 'probable'],
            'CRS-BR-2CH-012' => ['c. 873-849 a.C.', 'probable'],
            'CRS-BR-2CH-013' => ['c. 853 a.C.', 'probable'],
            'CRS-BR-2CH-014' => ['c. 852-848 a.C.', 'probable'],
        ];

        if (isset($secondChronicles[$source])) {
            return $this->make($secondChronicles[$source][0], $secondChronicles[$source][1]);
        }

        $secondKings = [
            'CRS-2K-001' => ['c. 852 a.C.', 'probable'],
            'CRS-2K-002' => ['c. 850 a.C.', 'probable'],
            'CRS-2K-003' => ['c. 849 a.C.', 'probable'],
            'CRS-2K-004' => ['c. 850-840 a.C.', 'probable'],
            'CRS-2K-005' => ['c. 850-840 a.C.', 'probable'],
            'CRS-2K-006' => ['c. 850-840 a.C.', 'probable'],
            'CRS-2K-007' => ['c. 850-840 a.C.', 'probable'],
            'CRS-2K-008A' => ['c. 843 a.C.', 'probable'],
            'CRS-2K-008B' => ['c. 848-841 a.C.', 'probable'],
            'CRS-2K-009' => ['c. 841 a.C.', 'alta'],
            'CRS-2K-010' => ['c. 841 a.C.', 'probable'],
            'CRS-2K-011' => ['c. 835 a.C.', 'alta'],
            'CRS-2K-012' => ['c. 835-796 a.C.', 'probable'],
            'CRS-2K-013' => ['c. 800 a.C.', 'probable'],
            'CRS-2K-014' => ['c. 793-753 a.C.', 'probable'],
            'CRS-2K-015' => ['c. 792-735 a.C.', 'probable'],
            'CRS-2K-016' => ['c. 735-715 a.C.', 'probable'],
            'CRS-2K-018' => ['c. 715-701 a.C.', 'probable'],
            'CRS-2K-020' => ['c. 701-686 a.C.', 'probable'],
            'CRS-2K-021' => ['c. 697-640 a.C.', 'probable'],
            'CRS-2K-023' => ['c. 622-609 a.C.', 'probable'],
            'CRS-2K-024' => ['c. 609-597 a.C.', 'probable'],
        ];

        if (isset($secondKings[$source])) {
            return $this->make($secondKings[$source][0], $secondKings[$source][1]);
        }

        return null;
    }

    private function psalterEstimate(string $source): ?array
    {
        if (! str_starts_with($source, 'CRS-PSA-')) {
            return null;
        }

        $collectionRules = [
            '/^CRS-PSA-00[1]$/' => ['coleccion final del Salterio: c. s. V-III a.C.', 'debatida'],
            '/^CRS-PSA-00[3-9]$/' => ['c. 1010-970 a.C.', 'debatida'],
            '/^(CRS-PSA-011|CRS-PSA-014|CRS-PSA-016|CRS-PSA-018|CRS-PSA-019)$/' => ['c. 1010-970 a.C.', 'debatida'],
            '/^CRS-PSA-02[2-4]$/' => ['c. 1010-970 a.C.', 'debatida'],
            '/^CRS-PSA-02[68]$/' => ['coleccion del templo: c. 1000-400 a.C.', 'debatida'],
            '/^(CRS-PSA-029|CRS-PSA-030|CRS-PSA-031|CRS-PSA-032|CRS-PSA-033)$/' => ['coleccion del Salterio: c. 1000-400 a.C.', 'debatida'],
            '/^CRS-PSA-03[45789]|04[0-1]$/' => ['cantos de peregrinacion: c. 1000-400 a.C.', 'debatida'],
            '/^CRS-PSA-04[23]$/' => ['coleccion final del Salterio: c. s. V-III a.C.', 'debatida'],
        ];

        foreach ($collectionRules as $pattern => [$date, $confidence]) {
            if (preg_match($pattern, $source)) {
                return $this->make($date, $confidence);
            }
        }

        return $this->make('coleccion del Salterio: fecha debatida', 'debatida');
    }

    private function prophetsEstimate(string $source): ?array
    {
        if (! preg_match('/^CRS-0[456]-/', $source)) {
            return null;
        }

        $ranges = [
            '/^CRS-04-00[1]$/' => ['c. 780-760 a.C.', 'debatida'],
            '/^CRS-04-00[2-4]$/' => ['c. 760-750 a.C.', 'probable'],
            '/^CRS-04-00[5-7]$/' => ['c. 750-722 a.C.', 'probable'],
            '/^CRS-04-00[89]$/' => ['c. 740-735 a.C.', 'probable'],
            '/^CRS-04-01[0-2]$/' => ['c. 735-700 a.C.', 'probable'],
            '/^CRS-04-013$/' => ['c. 734-732 a.C.', 'alta'],
            '/^CRS-04-01[45]$/' => ['c. 730-701 a.C.', 'probable'],
            '/^CRS-04-01[6-8]$/' => ['c. 705-701 a.C.', 'alta'],
            '/^CRS-04-019$/' => ['c. 663-612 a.C.', 'probable'],
            '/^CRS-04-02[0-2]$/' => ['c. 640-609 a.C.', 'probable'],
            '/^CRS-04-023$/' => ['c. 609 a.C.', 'probable'],
            '/^CRS-04-02[4-7]$/' => ['c. 609-605 a.C.', 'probable'],
            '/^CRS-04-029$/' => ['c. 605-601 a.C.', 'probable'],
            '/^CRS-04-03[0-3]$/' => ['c. 597-588 a.C.', 'probable'],
            '/^CRS-04-035$/' => ['c. 594-593 a.C.', 'probable'],
            '/^CRS-04-036$/' => ['c. 588-586 a.C.', 'probable'],
            '/^CRS-04-038$/' => ['c. 588-586 a.C.', 'probable'],
            '/^CRS-04-04[0-5]$/' => ['c. 586-580 a.C.', 'alta'],
            '/^CRS-04-04[67]$/' => ['c. 540 a.C.', 'debatida'],
            '/^(CRS-04-048|CRS-04-049|CRS-04-050|CRS-04-051)$/' => ['c. 539-500 a.C.', 'debatida'],
            '/^CRS-05-001$/' => ['c. 609-605 a.C.', 'probable'],
            '/^CRS-05-003$/' => ['c. 603-562 a.C.', 'probable'],
            '/^CRS-05-00[5-6]$/' => ['c. 592 a.C.', 'alta'],
            '/^CRS-05-007$/' => ['c. 588-586 a.C.', 'alta'],
            '/^CRS-05-008$/' => ['c. 586 a.C.', 'alta'],
            '/^CRS-05-009$/' => ['c. 593-571 a.C.', 'probable'],
            '/^CRS-05-010$/' => ['c. 587 a.C.', 'alta'],
            '/^CRS-05-011$/' => ['c. 587-571 a.C.', 'probable'],
            '/^CRS-05-012$/' => ['c. 585 a.C.', 'alta'],
            '/^CRS-05-013$/' => ['c. 586 a.C.', 'probable'],
            '/^CRS-05-014$/' => ['c. 585-573 a.C.', 'probable'],
            '/^CRS-05-015$/' => ['c. 573 a.C.', 'alta'],
            '/^CRS-05-016$/' => ['c. 571 a.C.', 'alta'],
            '/^CRS-05-017$/' => ['c. 586-550 a.C.', 'debatida'],
            '/^CRS-05-018$/' => ['c. 553-539 a.C.', 'probable'],
            '/^CRS-05-02[01]$/' => ['c. 539-538 a.C.', 'alta'],
            '/^CRS-05-023$/' => ['c. 538-536 a.C.', 'alta'],
            '/^CRS-05-024$/' => ['c. 536-520 a.C.', 'probable'],
            '/^CRS-05-025$/' => ['c. 536 a.C.', 'alta'],
            '/^(CRS-05-027|CRS-05-028|CRS-05-029|CRS-05-030)$/' => ['c. 520-518 a.C.', 'alta'],
            '/^CRS-05-031$/' => ['c. 520 a.C.', 'alta'],
            '/^CRS-05-033$/' => ['c. 520-480 a.C.', 'debatida'],
            '/^CRS-05-034$/' => ['c. 486-465 a.C.', 'probable'],
            '/^CRS-05-035$/' => ['c. 465-424 a.C.', 'probable'],
            '/^CRS-05-037$/' => ['c. 458-457 a.C.', 'probable'],
            '/^(CRS-05-039|CRS-05-040|CRS-05-041|CRS-05-042|CRS-05-043|CRS-05-044)$/' => ['c. 445-432 a.C.', 'probable'],
            '/^CRS-05-045$/' => ['c. 450-430 a.C.', 'debatida'],
            '/^CRS-06-00[1-4]$/' => ['c. 483-473 a.C.', 'probable'],
        ];

        foreach ($ranges as $pattern => [$date, $confidence]) {
            if (preg_match($pattern, $source)) {
                return $this->make($date, $confidence);
            }
        }

        return null;
    }

    private function gospelEstimate(string $source): ?array
    {
        if (! preg_match('/^CRS-07-(\d{3})$/', $source, $matches)) {
            return $source === 'CRS-GAP-MAT-022'
                ? $this->make('c. 30 d.C.', 'probable')
                : null;
        }

        $n = (int) $matches[1];

        return match (true) {
            $n <= 3 => $this->make('marco literario; sin fecha de evento', 'debatida'),
            $n <= 7 => $this->make('c. 6-4 a.C.', 'debatida'),
            $n === 8 => $this->make('c. 8 d.C.', 'debatida'),
            $n <= 18 => $this->make('c. 27-28 d.C.', 'probable'),
            $n <= 40 => $this->make('c. 28 d.C.', 'probable'),
            $n <= 54 => $this->make('c. 29 d.C.', 'probable'),
            $n <= 59 => $this->make('c. 29-30 d.C.', 'probable'),
            $n <= 74 => $this->make('c. 30 d.C.', 'probable'),
            default => $this->make('c. 30 d.C.', 'alta'),
        };
    }

    private function actsAndLettersEstimate(string $source): ?array
    {
        $exact = [
            'CRS-NT-001' => ['c. 30 d.C.', 'alta'],
            'CRS-NT-002' => ['c. 30 d.C.', 'alta'],
            'CRS-NT-003' => ['c. 30-33 d.C.', 'probable'],
            'CRS-NT-004' => ['c. 30-34 d.C.', 'probable'],
            'CRS-NT-005' => ['c. 34-35 d.C.', 'probable'],
            'CRS-NT-006' => ['c. 35-36 d.C.', 'probable'],
            'CRS-NT-007' => ['c. 33-36 d.C.', 'debatida'],
            'CRS-NT-008' => ['c. 40-41 d.C.', 'probable'],
            'CRS-NT-009' => ['c. 41-44 d.C.', 'probable'],
            'CRS-NT-010' => ['c. 44 d.C.', 'alta'],
            'CRS-NT-011' => ['c. 46-48 d.C.', 'probable'],
            'CRS-NT-012' => ['c. 46-48 d.C.', 'probable'],
            'CRS-NT-013' => ['c. 48-49 d.C.', 'debatida'],
            'CRS-NT-014' => ['c. 49 d.C.', 'probable'],
            'CRS-NT-015' => ['c. 49-50 d.C.', 'probable'],
            'CRS-NT-016' => ['c. 49-50 d.C.', 'probable'],
            'CRS-NT-017' => ['c. 50 d.C.', 'probable'],
            'CRS-NT-018' => ['c. 50-51 d.C.', 'probable'],
            'CRS-NT-019' => ['c. 50-52 d.C.', 'probable'],
            'CRS-NT-020' => ['c. 50-51 d.C.', 'probable'],
            'CRS-NT-021' => ['c. 51-52 d.C.', 'probable'],
            'CRS-NT-022' => ['c. 52 d.C.', 'probable'],
            'CRS-NT-023' => ['c. 53-55 d.C.', 'probable'],
            'CRS-NT-024' => ['c. 53-55 d.C.', 'probable'],
            'CRS-NT-025' => ['c. 55-56 d.C.', 'probable'],
            'CRS-NT-026' => ['c. 55-56 d.C.', 'probable'],
            'CRS-NT-027' => ['c. 56-57 d.C.', 'probable'],
            'CRS-NT-028' => ['c. 57 d.C.', 'probable'],
            'CRS-NT-029' => ['c. 57 d.C.', 'probable'],
            'CRS-NT-030' => ['c. 57-58 d.C.', 'probable'],
            'CRS-NT-031' => ['c. 58-60 d.C.', 'probable'],
            'CRS-NT-032' => ['c. 60-61 d.C.', 'probable'],
            'CRS-NT-033' => ['c. 60-62 d.C.', 'probable'],
            'CRS-NT-034' => ['c. 60-62 d.C.', 'probable'],
            'CRS-NT-035' => ['c. 60-62 d.C.', 'probable'],
            'CRS-NT-036' => ['c. 60-62 d.C.', 'probable'],
            'CRS-NT-037' => ['c. 60-62 d.C.', 'probable'],
            'CRS-NT-038' => ['c. 63-65 d.C.', 'debatida'],
            'CRS-NT-039' => ['c. 63-65 d.C.', 'debatida'],
            'CRS-NT-040' => ['c. 66-67 d.C.', 'debatida'],
        ];

        if (isset($exact[$source])) {
            return $this->make($exact[$source][0], $exact[$source][1]);
        }

        if (preg_match('/^CRS-G(EN|LET)-00[1-5]$/', $source)) {
            return $this->make('c. 60-70 d.C.', 'debatida');
        }

        if (preg_match('/^CRS-G(EN|LET)-00[6-7]$/', $source)) {
            return $this->make('c. 45-62 d.C.', 'debatida');
        }

        if (preg_match('/^CRS-G(EN|LET)-00[8-9]$/', $source)) {
            return $this->make('c. 62-64 d.C.', 'probable');
        }

        if (preg_match('/^CRS-G(EN|LET)-010$/', $source)) {
            return $this->make('c. 64-68 d.C.', 'debatida');
        }

        if (preg_match('/^CRS-G(EN|LET)-01[1-4]$/', $source)) {
            return $this->make('c. 85-95 d.C.', 'debatida');
        }

        if (preg_match('/^CRS-G(EN|LET)-015$/', $source)) {
            return $this->make('c. 60-80 d.C.', 'debatida');
        }

        if (preg_match('/^CRS-REV-\d{3}$/', $source)) {
            return $this->make('c. 95-96 d.C.', 'debatida');
        }

        return null;
    }

    private function eraEstimate(string $slug): ?array
    {
        $byEra = [
            'primeval-history' => ['tiempo primordial; no fechable', 'especulativa'],
            'patriarchs' => ['c. 2100-1800 a.C.', 'debatida'],
            'exodus-sinai' => ['c. 1446-1445 a.C.', 'debatida'],
            'wilderness' => ['c. 1445-1406 a.C.', 'debatida'],
            'plains-of-moab' => ['c. 1406 a.C.', 'debatida'],
            'conquest-settlement' => ['c. 1406-1375 a.C.', 'debatida'],
            'judges' => ['c. 1375-1050 a.C.', 'debatida'],
            'rise-of-the-monarchy' => ['c. 1050-1010 a.C.', 'probable'],
            'united-monarchy' => ['c. 1010-931 a.C.', 'probable'],
            'divided-monarchy' => ['c. 930-852 a.C.', 'probable'],
            'divided-kingdom' => ['c. 930-722 a.C.', 'probable'],
            'genealogical-retrospective' => ['retrospectiva genealogica; sin fecha unica', 'debatida'],
            'postexilic-retrospective' => ['c. 450-400 a.C.', 'debatida'],
            'wisdom-literature-chronology-unresolved' => ['sin fecha segura', 'especulativa'],
            'solomonic-wisdom-tradition' => ['c. 970-931 a.C.', 'debatida'],
            'hezekiahs-reign-collection-note' => ['c. 715-686 a.C.', 'probable'],
            'wisdom-collection' => ['fecha no segura', 'debatida'],
            'judah-under-assyria' => ['c. 715-686 a.C.', 'probable'],
            'judahs-final-reforms' => ['c. 622-609 a.C.', 'probable'],
            'babylonian-ascent' => ['c. 609-597 a.C.', 'probable'],
            'babylonian-exile' => ['c. 605-539 a.C.', 'probable'],
            'late-judah' => ['c. 609-605 a.C.', 'probable'],
            'persian-transition' => ['c. 539-536 a.C.', 'probable'],
            'return-and-reconstruction' => ['c. 538-516 a.C.', 'probable'],
            'postexilic-prophetic-collection' => ['c. 520-430 a.C.', 'debatida'],
            'persian-period' => ['c. 486-432 a.C.', 'probable'],
            'intertestamental-period' => ['c. 430-5 a.C.', 'probable'],
            'apocalyptic-witness' => ['c. 95-96 d.C.', 'debatida'],
            'correspondence-beyond-acts' => ['c. 45-95 d.C.', 'debatida'],
            'chronological-placement-unresolved' => ['sin fecha segura', 'especulativa'],
        ];

        return isset($byEra[$slug])
            ? $this->make($byEra[$slug][0], $byEra[$slug][1])
            : null;
    }

    private function fallbackEstimate(ChronologicalReadingSet $crs): ?array
    {
        if (str_starts_with($crs->source_map, 'CRS-WIS-')) {
            return $this->make('fecha sapiencial no segura', 'debatida');
        }

        if (str_starts_with($crs->source_map, 'CRS-PSA-')) {
            return $this->make('coleccion del Salterio: fecha debatida', 'debatida');
        }

        return null;
    }

    private function make(string $start, string $confidence, ?string $end = null): array
    {
        [$yearStart, $yearEnd] = $this->parseYearRange($start);

        return [
            'start' => $start,
            'end' => $end,
            'confidence' => $confidence,
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ];
    }

    private function parseYearRange(string $label): array
    {
        $isBce = str_contains($label, 'a.C.');
        $isCe = str_contains($label, 'd.C.');

        if (! $isBce && ! $isCe) {
            return [null, null];
        }

        if (! preg_match('/(\d{1,4})(?:-(\d{1,4}))?/', $label, $matches)) {
            return [null, null];
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) ? (int) $matches[2] : $start;

        if ($isBce) {
            $start *= -1;
            $end *= -1;
        }

        return [$start, $end];
    }
}
