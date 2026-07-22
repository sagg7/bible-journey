<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudioNarration;
use App\Models\BiblicalBook;
use App\Models\BibleChapter;
use App\Models\BibleVerse;
use App\Models\ParallelLink;
use App\Models\ReadingBlock;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    /**
     * GET /api/readings/{blockId}?translation=RVA1909
     *
     * Returns a ReadingBlock with its full verse text,
     * editorial metadata, prev/next blocks, and related blocks.
     */
    public function show(Request $request, int $blockId): JsonResponse
    {
        $block = ReadingBlock::with([
            'crs',
            'startBook',
            'endBook',
        ])->findOrFail($blockId);

        $includeTestOnly = (bool) ($request->user('sanctum')?->has_test_access);
        $translationCode = $request->query('translation', 'RVA1909');
        $translation = Translation::where('code', $translationCode)
            ->where('can_display_full_text', true)
            ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false))
            ->first()
            ?? Translation::where('can_display_full_text', true)
                ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false))
                ->orderBy('sort_order')->first();

        if (! $translation) {
            return response()->json(['error' => 'No hay traducción con texto completo disponible.'], 503);
        }

        $verses = $this->fetchVerses($block, $translation->id);

        // Prev / next block in the same CRS (by display_order)
        $prevBlock = ReadingBlock::where('crs_id', $block->crs_id)
            ->where('display_order', '<', $block->display_order)
            ->orderByDesc('display_order')
            ->first(['id', 'display_label_es', 'display_reference']);

        $nextBlock = ReadingBlock::where('crs_id', $block->crs_id)
            ->where('display_order', '>', $block->display_order)
            ->orderBy('display_order')
            ->first(['id', 'display_label_es', 'display_reference']);

        // Related blocks via parallel_links
        $relatedIds = ParallelLink::where('source_block_id', $blockId)
            ->orWhere('target_block_id', $blockId)
            ->where('approved', true)
            ->get()
            ->flatMap(fn($l) => [$l->source_block_id, $l->target_block_id])
            ->unique()
            ->reject(fn($id) => $id === $blockId)
            ->values();

        $relatedBlocks = ReadingBlock::whereIn('id', $relatedIds)
            ->get(['id', 'display_label_es', 'display_reference', 'role', 'placement_confidence']);

        // Editorial context from CRS
        $crs = $block->crs;

        return response()->json([
            'block' => [
                'id'                => $block->id,
                'display_reference' => $block->display_reference,
                'display_label_es'  => $block->display_label_es,
                'role'              => $block->role,
                'start_chapter'     => $block->start_chapter,
                'start_verse'       => $block->start_verse,
                'end_chapter'       => $block->end_chapter,
                'end_verse'         => $block->end_verse,
                'confidence'        => $block->placement_confidence,
                'audio_narration'   => $this->audioNarrationPayload($block, $request, $includeTestOnly),
            ],
            'book' => $block->startBook ? [
                'id'         => $block->startBook->id,
                'osis_code'  => $block->startBook->osis_code,
                'name_es'    => $block->startBook->name_es,
                'name_en'    => $block->startBook->name_en,
                'testament'  => $block->startBook->testament,
            ] : null,
            'translation' => [
                'code'  => $translation->code,
                'name'  => $translation->name,
                'label' => $translation->license_label,
            ],
            'has_text'       => ! empty($verses),
            'verse_count'    => count($verses),
            'verses'         => $verses,
            'editorial' => $crs ? [
                'title_es'                  => $crs->title_es,
                'era'                       => $crs->era,
                'placement_confidence'      => $crs->placement_confidence,
                'event_confidence'          => $crs->event_confidence,
                'narrative_flow_message_es' => $crs->narrative_flow_message_es,
                'editorial_note'            => $crs->editorial_note,
            ] : null,
            'navigation' => [
                'prev_block' => $prevBlock,
                'next_block' => $nextBlock,
            ],
            'related_blocks' => $relatedBlocks,
        ]);
    }

    /**
     * GET /api/readings/book/{osisCode}/chapter/{number}?translation=RVA1909
     *
     * Returns a full chapter for canonical reading.
     */
    public function chapter(Request $request, string $osisCode, int $chapterNumber): JsonResponse
    {
        $book = BiblicalBook::where('osis_code', strtoupper($osisCode))->firstOrFail();

        $chapter = BibleChapter::where('biblical_book_id', $book->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();

        $includeTestOnly = (bool) ($request->user('sanctum')?->has_test_access);
        $translationCode = $request->query('translation', 'RVA1909');
        $translation = Translation::where('code', $translationCode)
            ->where('can_display_full_text', true)
            ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false))
            ->first()
            ?? Translation::where('can_display_full_text', true)
                ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false))
                ->orderBy('sort_order')->first();

        if (! $translation) {
            return response()->json(['error' => 'No hay traducción disponible.'], 503);
        }

        $verses = BibleVerse::where('chapter_id', $chapter->id)
            ->where('translation_id', $translation->id)
            ->orderBy('verse_number')
            ->get(['verse_number', 'text'])
            ->map(fn($v) => ['number' => $v->verse_number, 'text' => $v->text]);

        $prevChapter = $chapterNumber > 1 ? $chapterNumber - 1 : null;
        $nextChapter = $chapterNumber < $book->chapter_count ? $chapterNumber + 1 : null;

        return response()->json([
            'book' => [
                'osis_code'     => $book->osis_code,
                'name_es'       => $book->name_es,
                'chapter_count' => $book->chapter_count,
            ],
            'chapter'      => $chapterNumber,
            'verse_count'  => $chapter->verse_count,
            'translation'  => ['code' => $translation->code, 'name' => $translation->name],
            'has_text'     => $verses->isNotEmpty(),
            'verses'       => $verses,
            'navigation'   => [
                'prev_chapter' => $prevChapter,
                'next_chapter' => $nextChapter,
            ],
        ]);
    }

    /**
     * GET /api/readings/books?translation=RVA1909
     *
     * Returns all 66 books with chapter count and text availability.
     */
    public function books(Request $request): JsonResponse
    {
        $translationCode = $request->query('translation', 'RVA1909');
        $translation = Translation::where('code', $translationCode)->first();

        // Un solo query para saber qué libros tienen texto en esta traducción
        // (antes: un EXISTS por libro — 66 queries).
        $bookIdsWithText = $translation
            ? \Illuminate\Support\Facades\DB::table('bible_verses')
                ->join('bible_chapters', 'bible_chapters.id', '=', 'bible_verses.chapter_id')
                ->where('bible_verses.translation_id', $translation->id)
                ->distinct()
                ->pluck('bible_chapters.biblical_book_id')
                ->flip()
            : collect();

        $books = BiblicalBook::orderBy('canonical_order')
            ->get()
            ->map(function ($book) use ($bookIdsWithText) {
                $hasText = $bookIdsWithText->has($book->id);

                return [
                    'id'            => $book->id,
                    'osis_code'     => $book->osis_code,
                    'slug'          => $book->slug,
                    'name_es'       => $book->name_es,
                    'name_en'       => $book->name_en,
                    'testament'     => $book->testament,
                    'canonical_order'=> $book->canonical_order,
                    'chapter_count' => $book->chapter_count,
                    'has_text'      => $hasText,
                ];
            });

        return response()->json(['data' => $books, 'translation' => $translationCode]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function fetchVerses(ReadingBlock $block, int $translationId): array
    {
        if (! $block->startBook || ! $block->start_chapter) {
            return [];
        }

        // Single-book range (most common case)
        if (
            $block->start_book_id === $block->end_book_id
            || $block->end_book_id === null
        ) {
            return $this->fetchVersesInRange(
                $block->startBook->id,
                $block->start_chapter,
                $block->start_verse,
                $block->end_chapter ?? $block->start_chapter,
                $block->end_verse,
                $translationId
            );
        }

        // Cross-book range (rare but possible in parallel accounts)
        $verses = [];
        $verses = array_merge($verses, $this->fetchVersesInRange(
            $block->start_book_id, $block->start_chapter, $block->start_verse, null, null, $translationId,
            endAll: true
        ));
        // Intermediate books would go here (skipped for simplicity — no CRS spans 3+ books)
        $verses = array_merge($verses, $this->fetchVersesInRange(
            $block->end_book_id, 1, null, $block->end_chapter, $block->end_verse, $translationId
        ));

        return $verses;
    }

    private function audioNarrationPayload(ReadingBlock $block, Request $request, bool $includeTestOnly): ?array
    {
        $audioTranslationCode = $request->query('audio_translation', $request->query('translation', 'NVI'));
        $audioTranslation = Translation::where('code', $audioTranslationCode)
            ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false))
            // Gate de licencia: no servir narraciones de traducciones sin
            // licencia comprobada (p.ej. NVI) fuera de cuentas de prueba.
            ->when(! $includeTestOnly, fn ($q) => $q->where('can_display_full_text', true))
            ->first();

        if (! $audioTranslation) {
            return null;
        }

        $audio = AudioNarration::where('reading_block_id', $block->id)
            ->where('translation_id', $audioTranslation->id)
            ->where('provider', $request->query('audio_provider', 'gemini'))
            ->where('voice', $request->query('audio_voice', 'Charon'))
            ->where('status', 'success')
            ->orderByDesc('generated_at')
            ->first();

        if (! $audio) {
            return null;
        }

        return [
            'id' => $audio->id,
            'provider' => $audio->provider,
            'voice' => $audio->voice,
            'model' => $audio->model,
            'url' => $audio->publicUrl(),
            'mime_type' => $audio->mime_type,
            'duration_seconds' => $audio->duration_seconds,
            'byte_size' => $audio->byte_size,
            'generated_at' => $audio->generated_at?->toIsoString(),
        ];
    }

    private function fetchVersesInRange(
        int $bookId,
        int $startChapter,
        ?int $startVerse,
        ?int $endChapter,
        ?int $endVerse,
        int $translationId,
        bool $endAll = false
    ): array {
        $endChapter ??= $startChapter;

        $chapters = BibleChapter::where('biblical_book_id', $bookId)
            ->whereBetween('chapter_number', [$startChapter, $endAll ? 999 : $endChapter])
            ->orderBy('chapter_number')
            ->pluck('id', 'chapter_number');

        if ($chapters->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($chapters as $chNum => $chId) {
            $query = BibleVerse::where('chapter_id', $chId)
                ->where('translation_id', $translationId)
                ->orderBy('verse_number');

            // Apply verse constraints on boundary chapters
            if ($chNum === $startChapter && $startVerse !== null) {
                $query->where('verse_number', '>=', $startVerse);
            }
            if ($chNum === $endChapter && $endVerse !== null && ! $endAll) {
                $query->where('verse_number', '<=', $endVerse);
            }

            $verses = $query->get(['verse_number', 'text']);
            foreach ($verses as $v) {
                $result[] = [
                    'chapter' => $chNum,
                    'verse'   => $v->verse_number,
                    'text'    => $v->text,
                    'ref'     => "{$chNum}:{$v->verse_number}",
                ];
            }
        }

        return $result;
    }
}
