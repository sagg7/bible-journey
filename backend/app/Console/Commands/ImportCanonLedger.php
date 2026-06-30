<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\ChronologicalReadingSet;
use App\Models\ReadingBlock;
use App\Models\ParallelLink;
use App\Models\CompareGroup;
use App\Models\EvidenceRecord;
use App\Models\EditorialDecision;
use App\Models\LedgerSnapshot;

class ImportCanonLedger extends Command
{
    protected $signature   = 'import:canon-ledger
                                {file : Path to the Master Canon Ledger XLSX}
                                {--pilots=DAV,HZK,GOS : Comma-separated pilot codes to import}
                                {--dry-run : Parse and validate without writing to DB}
                                {--force : Re-import even if snapshot already exists}';

    protected $description = 'Import Chronological Reading Sets from the Master Canon Ledger XLSX';

    // Sheet names expected in the workbook
    const SHEET_CRS    = 'Master CRS Ledger';
    const SHEET_BLOCKS = 'Master Reading Blocks';
    const SHEET_LINKS  = 'Master Link Registry';
    const SHEET_DECS   = 'Open Decisions Master';

    private array $pilots = [];
    private bool  $dryRun = false;
    private array $stats  = ['crs' => 0, 'blocks' => 0, 'links' => 0, 'decisions' => 0, 'skipped' => 0, 'errors' => []];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: $file");
            return self::FAILURE;
        }

        $this->pilots = array_map('trim', explode(',', $this->option('pilots')));
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        $this->info('Loading workbook…');
        $spreadsheet = IOFactory::load($file);

        // Compute snapshot hash
        $snapshotId = hash('sha256', file_get_contents($file));

        if (! $this->dryRun) {
            $existing = LedgerSnapshot::where('snapshot_id', $snapshotId)->where('status', 'imported')->first();
            if ($existing && ! $this->option('force')) {
                $this->warn("Snapshot $snapshotId already imported. Use --force to re-import.");
                return self::SUCCESS;
            }
        }

        $this->info('Importing pilots: ' . implode(', ', $this->pilots));

        DB::transaction(function () use ($spreadsheet, $snapshotId, $file) {
            $this->importCrs($spreadsheet);
            $this->importBlocks($spreadsheet);
            $this->importLinks($spreadsheet);
            $this->importDecisions($spreadsheet);

            if (! $this->dryRun) {
                LedgerSnapshot::updateOrCreate(
                    ['snapshot_id' => $snapshotId],
                    [
                        'source_file'     => basename($file),
                        'ledger_version'  => '1.0',
                        'crs_count'       => $this->stats['crs'],
                        'block_count'     => $this->stats['blocks'],
                        'link_count'      => $this->stats['links'],
                        'decision_count'  => $this->stats['decisions'],
                        'imported_pilots' => $this->pilots,
                        'status'          => 'imported',
                        'imported_at'     => now(),
                    ]
                );
            }
        });

        $this->printReport($snapshotId);

        return empty($this->stats['errors']) ? self::SUCCESS : self::FAILURE;
    }

    // ─── CRS sheet ────────────────────────────────

    // Row 4 = headers, Row 5+ = data in every CRS sheet
    const HEADER_ROW = 4;
    const DATA_START = 5;

    private function importCrs($spreadsheet): void
    {
        $sheet = $this->getSheet($spreadsheet, self::SHEET_CRS);
        if (! $sheet) {
            $this->warn('Sheet "' . self::SHEET_CRS . '" not found — skipping CRS.');
            return;
        }

        $headers = $this->getHeaders($sheet, self::HEADER_ROW);
        $this->info('  Importing CRS…');

        foreach ($sheet->getRowIterator(self::DATA_START) as $row) {
            $data = $this->rowToArray($sheet, $row, $headers);
            $sourceMap = trim($data['local_crs_id'] ?? $data['crs_id'] ?? $data['source_map'] ?? '');
            if (empty($sourceMap)) continue;

            // Filter by pilot
            $pilot = $this->getPilotCode($sourceMap);
            if (! $this->pilotAllowed($pilot)) {
                $this->stats['skipped']++;
                continue;
            }

            // Ledger column names (normalized): local_crs_id, era_window, map_sequence,
            // local_order, reading_set_title, event_confidence, placement_confidence, editorial_note
            $era      = $data['era_window'] ?? $data['era'] ?? $data['era_es'] ?? 'Sin era';
            $sortKey  = (int) ($data['map_sequence'] ?? $data['local_order'] ?? $data['sort_key'] ?? 0);
            $titleEs  = $data['reading_set_title'] ?? $data['title_es'] ?? $data['title'] ?? $sourceMap;

            $record = [
                'source_map'          => $sourceMap,
                'era'                 => $era,
                'era_slug'            => Str::slug($era),
                'sort_key'            => $sortKey,
                'title_es'            => $titleEs,
                'title_en'            => $data['title_en'] ?? null,
                'placement_confidence'=> $this->mapConfidence($data['placement_confidence'] ?? ''),
                'event_confidence'    => $this->mapConfidence($data['event_confidence'] ?? ''),
                'relation_confidence' => $this->mapConfidence($data['relation_confidence'] ?? $data['event_confidence'] ?? ''),
                'review_status'       => 'needs_review',
                'editorial_version'   => '1.0',
                'narrative_flow_message_es' => $data['narrative_flow_rule'] ?? $data['narrative_flow_message_es'] ?? null,
                'transition_copy_es'  => null,
                'editorial_note'      => $data['editorial_note'] ?? $data['notes'] ?? null,
                'canon_profile'       => 'cautious_default',
            ];

            if (! $this->dryRun) {
                ChronologicalReadingSet::updateOrCreate(
                    ['source_map' => $sourceMap],
                    $record
                );
            }
            $this->stats['crs']++;
        }

        $this->line("    → {$this->stats['crs']} CRS");
    }

    // ─── Blocks sheet ─────────────────────────────

    private function importBlocks($spreadsheet): void
    {
        $sheet = $this->getSheet($spreadsheet, self::SHEET_BLOCKS);
        if (! $sheet) {
            $this->warn('Sheet "' . self::SHEET_BLOCKS . '" not found — skipping blocks.');
            return;
        }

        $headers = $this->getHeaders($sheet, self::HEADER_ROW);
        $this->info('  Importing reading blocks…');

        foreach ($sheet->getRowIterator(self::DATA_START) as $row) {
            $data = $this->rowToArray($sheet, $row, $headers);
            $sourceMap = trim($data['local_block_id'] ?? $data['block_id'] ?? $data['source_map'] ?? '');
            $crsMap    = trim($data['parent_crs_id'] ?? $data['crs_id'] ?? '');
            if (empty($sourceMap) || empty($crsMap)) continue;

            $pilot = $this->getPilotCode($crsMap);
            if (! $this->pilotAllowed($pilot)) continue;

            if (! $this->dryRun) {
                $crs = ChronologicalReadingSet::where('source_map', $crsMap)->first();
                if (! $crs) {
                    $this->stats['errors'][] = "CRS not found for block $sourceMap (parent_crs_id=$crsMap)";
                    continue;
                }

                // passage_reference in ledger is "16:1-13" or "1-3" without book
                $passageRef = $data['passage_reference'] ?? $data['display_reference'] ?? '';
                $book       = $data['book'] ?? '';
                $displayRef = $book ? "$book $passageRef" : $passageRef;

                ReadingBlock::updateOrCreate(
                    ['source_map' => $sourceMap],
                    [
                        'crs_id'                   => $crs->id,
                        'book'                     => $book,
                        'passage_start'            => $passageRef, // raw, structured later
                        'passage_end'              => $passageRef,
                        'display_reference'        => $displayRef,
                        'role'                     => $this->mapRole($data['block_role'] ?? $data['role'] ?? ''),
                        'display_order'            => (int) ($data['display_block_order'] ?? $data['display_order'] ?? 0),
                        'display_label_es'         => $data['end_user_label'] ?? $data['display_label_es'] ?? null,
                        'display_label_en'         => $data['end_user_label'] ?? null,
                        'required_in_complete_mode'=> $this->parseBool($data['required_in_full_stream'] ?? $data['required_in_complete_mode'] ?? 'true'),
                        'shown_in_narrative_flow'  => $this->parseBool($data['narrative_flow_default'] ?? $data['shown_in_narrative_flow'] ?? 'true'),
                        'placement_confidence'     => $this->mapConfidence($data['placement_confidence'] ?? $data['event_setting_confidence'] ?? ''),
                        'source_keys'              => $this->parseSourceKeys($data['source_keys'] ?? ''),
                    ]
                );
            }
            $this->stats['blocks']++;
        }

        $this->line("    → {$this->stats['blocks']} blocks");
    }

    // ─── Links sheet ──────────────────────────────

    private function importLinks($spreadsheet): void
    {
        $sheet = $this->getSheet($spreadsheet, self::SHEET_LINKS);
        if (! $sheet) {
            $this->warn('Sheet "' . self::SHEET_LINKS . '" not found — skipping links.');
            return;
        }

        $headers = $this->getHeaders($sheet, self::HEADER_ROW);
        $this->info('  Importing parallel links…');

        foreach ($sheet->getRowIterator(self::DATA_START) as $row) {
            $data      = $this->rowToArray($sheet, $row, $headers);
            $recordId  = trim($data['local_record_id'] ?? '');
            // Links are CRS-to-CRS: anchor_from holds a CRS ID or CRS block reference
            $anchorCrs  = trim($data['anchor_from'] ?? '');
            $targetCrs  = trim($data['target_related'] ?? '');
            if (empty($anchorCrs) || empty($targetCrs)) continue;

            // Extract pilot from the raw source summary or anchor
            $rawSummary = $data['raw_source_summary'] ?? '';
            preg_match('/source crs:\s*(CRS-[A-Z0-9]+-\d+)/i', $rawSummary, $sm);
            $sourceCrsMap = $sm[1] ?? '';
            $pilot = $this->getPilotCode($sourceCrsMap ?: $anchorCrs);
            if (! $this->pilotAllowed($pilot)) continue;

            if (! $this->dryRun) {
                // Link anchor block = narrative_anchor of source CRS
                // Link target block = narrative_anchor of target CRS
                preg_match('/target crs:\s*(CRS-[A-Z0-9]+-\d+)/i', $rawSummary, $tm);
                $targetCrsMap = $tm[1] ?? $targetCrs;

                $sourceCrs = ChronologicalReadingSet::where('source_map', $sourceCrsMap)->first();
                $targetCrsObj = ChronologicalReadingSet::where('source_map', $targetCrsMap)->first();

                if (! $sourceCrs || ! $targetCrsObj) {
                    $this->stats['errors'][] = "CRS not found for link $recordId ($sourceCrsMap → $targetCrsMap)";
                    continue;
                }

                $sourceBlock = $sourceCrs->blocks()->where('role', 'narrative_anchor')->first()
                    ?? $sourceCrs->blocks()->first();
                $targetBlock = $targetCrsObj->blocks()->where('role', 'narrative_anchor')->first()
                    ?? $targetCrsObj->blocks()->first();

                if (! $sourceBlock || ! $targetBlock) {
                    $this->stats['errors'][] = "Blocks not found for link $recordId";
                    continue;
                }

                ParallelLink::updateOrCreate(
                    [
                        'source_block_id' => $sourceBlock->id,
                        'target_block_id' => $targetBlock->id,
                        'relation_type'   => $this->mapEdgeType($data['relationship_policy'] ?? ''),
                    ],
                    [
                        'confidence'    => $this->mapConfidence($data['confidence'] ?? ''),
                        'evidence_note' => $data['raw_source_summary'] ?? null,
                        'approved'      => false,
                    ]
                );
            }
            $this->stats['links']++;
        }

        $this->line("    → {$this->stats['links']} links");
    }

    // ─── Decisions sheet ──────────────────────────

    private function importDecisions($spreadsheet): void
    {
        $sheet = $this->getSheet($spreadsheet, self::SHEET_DECS);
        if (! $sheet) {
            $this->warn('Sheet "' . self::SHEET_DECS . '" not found — skipping decisions.');
            return;
        }

        $headers = $this->getHeaders($sheet, self::HEADER_ROW);
        $this->info('  Importing editorial decisions…');

        foreach ($sheet->getRowIterator(self::DATA_START) as $row) {
            $data      = $this->rowToArray($sheet, $row, $headers);
            $sourceKey = trim($data['local_decision_id'] ?? $data['decision_id'] ?? $data['source_key'] ?? '');
            if (empty($sourceKey)) continue;

            if (! $this->dryRun) {
                EditorialDecision::updateOrCreate(
                    ['source_key' => $sourceKey],
                    [
                        'topic'          => $data['topic_scope'] ?? $data['topic'] ?? $sourceKey,
                        'status'         => $this->mapDecisionStatus($data['status'] ?? ''),
                        'impact_scope'   => 'single_crs',
                        'interim_policy' => $data['current_treatment_interim_policy'] ?? $data['interim_policy'] ?? null,
                        'owner'          => $data['owner'] ?? null,
                        'affected_crs'   => null,
                        'notes'          => $data['why_it_is_open_issue'] ?? $data['raw_source_summary'] ?? null,
                    ]
                );
            }
            $this->stats['decisions']++;
        }

        $this->line("    → {$this->stats['decisions']} decisions");
    }

    // ─── Helpers ──────────────────────────────────

    private function getSheet($spreadsheet, string $name)
    {
        try {
            return $spreadsheet->getSheetByName($name);
        } catch (\Exception) {
            return null;
        }
    }

    private function getHeaders($sheet, int $row): array
    {
        $headers = [];
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cell = $sheet->getCell($colLetter . $row);
            $val  = $cell->getValue();
            // Handle RichText objects
            if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $val = $val->getPlainText();
            }
            $val = (string) $val;
            if (trim($val) !== '') {
                // Normalize: lowercase, replace non-alphanumeric with _
                $headers[$col] = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $val), '_'));
            }
        }
        return $headers;
    }

    private function rowToArray($sheet, $row, array $headers): array
    {
        $data = [];
        $rowIndex = $row->getRowIndex();
        foreach ($headers as $col => $key) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cell = $sheet->getCell($colLetter . $rowIndex);
            $val  = $cell->getValue();
            if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $val = $val->getPlainText();
            }
            $data[$key] = (string) $val;
        }
        return $data;
    }

    private function getPilotCode(string $sourceMap): string
    {
        if (preg_match('/CRS-([A-Z0-9]+)-/i', $sourceMap, $m)) return strtoupper($m[1]);
        if (preg_match('/BLK-([A-Z0-9]+)-/i', $sourceMap, $m)) return strtoupper($m[1]);
        return '';
    }

    private function pilotAllowed(string $code): bool
    {
        return in_array('ALL', $this->pilots) || in_array($code, $this->pilots);
    }

    private function mapConfidence(string $raw): string
    {
        $map = [
            'high' => 'alta', 'alta' => 'alta',
            'probable' => 'probable',
            'debated' => 'debatida', 'debatida' => 'debatida',
            'traditional' => 'tradicion_popular', 'tradicion_popular' => 'tradicion_popular', 'popular' => 'tradicion_popular',
            'speculative' => 'especulativa', 'especulativa' => 'especulativa',
        ];
        return $map[strtolower(trim($raw))] ?? 'probable';
    }

    private function mapRole(string $raw): string
    {
        // Map ledger display labels to DB enum values
        $raw = strtolower(trim(preg_replace('/[^a-z ]/i', '', $raw)));
        if (str_contains($raw, 'primary') || str_contains($raw, 'anchor') || str_contains($raw, 'narrative anchor')) {
            return 'narrative_anchor';
        }
        if (str_contains($raw, 'parallel')) return 'parallel_account';
        if (str_contains($raw, 'complementary')) return 'complementary_account';
        if (str_contains($raw, 'prophetic')) return 'prophetic_context';
        if (str_contains($raw, 'poetic') || str_contains($raw, 'psalm') || str_contains($raw, 'literary')) return 'poetic_literary_mirror';
        if (str_contains($raw, 'legal') || str_contains($raw, 'covenant') || str_contains($raw, 'law')) return 'legal_covenant_context';
        if (str_contains($raw, 'genealog')) return 'genealogical_context';
        if (str_contains($raw, 'epistle') || str_contains($raw, 'letter') || str_contains($raw, 'epistolary')) return 'epistolary_context';
        if (str_contains($raw, 'supplement')) return 'supplementary_reading';
        return 'narrative_anchor';
    }

    private function mapEdgeType(string $raw): string
    {
        $known = [
            'SEQUENTIAL_DIRECT', 'PARALLEL_ACCOUNT', 'COMPLEMENTARY_ACCOUNT',
            'PROPHETIC_CONTEXT', 'POETIC_CONNECTION', 'EPISTOLARY_CONTEXT',
            'CANONICAL_FALLBACK', 'LITERARY_SEQUENCE', 'INTERTEXTUAL_REFERENCE',
        ];
        $upper = strtoupper(trim($raw));
        return in_array($upper, $known) ? $upper : 'PARALLEL_ACCOUNT';
    }

    private function mapDecisionStatus(string $raw): string
    {
        $map = ['open' => 'open', 'resolved' => 'resolved', 'deferred' => 'deferred', 'wont_resolve' => 'wont_resolve'];
        return $map[strtolower(trim($raw))] ?? 'open';
    }

    private function parseBool(string $raw): bool
    {
        return in_array(strtolower(trim($raw)), ['true', '1', 'yes', 'y', 'si', 'sí']);
    }

    private function parseSourceKeys(string $raw): ?array
    {
        if (empty(trim($raw))) return null;
        return array_map('trim', explode(',', $raw));
    }

    private function printReport(string $snapshotId): void
    {
        $this->newLine();
        $this->info('─────────────────────────────────────────');
        $this->info(' Import report');
        $this->info('─────────────────────────────────────────');
        $this->line(" Snapshot : $snapshotId");
        $this->line(" Pilots   : " . implode(', ', $this->pilots));
        $this->line(" CRS      : {$this->stats['crs']}");
        $this->line(" Blocks   : {$this->stats['blocks']}");
        $this->line(" Links    : {$this->stats['links']}");
        $this->line(" Decisions: {$this->stats['decisions']}");
        $this->line(" Skipped  : {$this->stats['skipped']}");

        if (! empty($this->stats['errors'])) {
            $this->newLine();
            $this->warn(' Errors (' . count($this->stats['errors']) . '):');
            foreach (array_slice($this->stats['errors'], 0, 10) as $e) {
                $this->line("  • $e");
            }
        }

        if ($this->dryRun) {
            $this->newLine();
            $this->warn(' DRY RUN — nothing was written to the database.');
        }
        $this->info('─────────────────────────────────────────');
    }
}
