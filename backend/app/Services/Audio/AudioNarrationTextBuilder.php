<?php

namespace App\Services\Audio;

use App\Models\BibleChapter;
use App\Models\BiblicalBook;
use App\Models\ReadingBlock;
use App\Models\Translation;

class AudioNarrationTextBuilder
{
    public const PROMPT_VERSION = 'charon-es-v1';

    /**
     * @return array{
     *   title:string,
     *   reference:string|null,
     *   verses:array<int, array{book:string, chapter:int, verse:int, text:string}>,
     *   segments:array<int, string>,
     *   source_hash:string,
     *   prompt:string,
     *   prompt_hash:string,
     * }
     */
    public function build(ReadingBlock $block, Translation $translation, string $voice, string $model, int $maxChars = 3200): array
    {
        $block->loadMissing('crs', 'startBook', 'endBook');

        $title = $this->title($block);
        $verses = $this->verses($block, $translation);
        $bodySegments = $this->bodySegments($verses, $maxChars);
        $segments = array_values(array_filter([
            $this->introSegment($block, $title),
            ...$bodySegments,
        ], fn ($segment) => trim((string) $segment) !== ''));

        $prompt = $this->prompt($voice);
        $sourcePayload = [
            'block_id' => $block->id,
            'translation' => $translation->code,
            'title' => $title,
            'reference' => $block->display_reference,
            'verses' => $verses,
        ];
        $promptPayload = [
            'version' => self::PROMPT_VERSION,
            'provider' => 'gemini',
            'voice' => $voice,
            'model' => $model,
            'prompt' => $prompt,
            'max_chars' => $maxChars,
        ];

        return [
            'title' => $title,
            'reference' => $block->display_reference,
            'verses' => $verses,
            'segments' => $segments,
            'source_hash' => hash('sha256', json_encode($sourcePayload, JSON_UNESCAPED_UNICODE)),
            'prompt' => $prompt,
            'prompt_hash' => hash('sha256', json_encode($promptPayload, JSON_UNESCAPED_UNICODE)),
        ];
    }

    private function title(ReadingBlock $block): string
    {
        return trim((string) (
            $block->display_label_es
            ?: $block->crs?->title_es
            ?: $block->display_reference
            ?: 'Lectura biblica'
        ));
    }

    private function introSegment(ReadingBlock $block, string $title): string
    {
        $parts = [$title];

        if ($block->display_reference && ! str_contains(mb_strtolower($title), mb_strtolower($block->display_reference))) {
            $parts[] = $block->display_reference;
        }

        return implode('. ', array_filter($parts)).'.';
    }

    /**
     * @return array<int, array{book:string, chapter:int, verse:int, text:string}>
     */
    private function verses(ReadingBlock $block, Translation $translation): array
    {
        if (! $block->start_book_id || ! $block->start_chapter) {
            return [];
        }

        $startBook = $block->startBook ?: BiblicalBook::find($block->start_book_id);
        $endBook = $block->endBook ?: ($block->end_book_id ? BiblicalBook::find($block->end_book_id) : $startBook);

        if (! $startBook || ! $endBook) {
            return [];
        }

        $bookStart = min((int) $startBook->canonical_order, (int) $endBook->canonical_order);
        $bookEnd = max((int) $startBook->canonical_order, (int) $endBook->canonical_order);
        $books = BiblicalBook::whereBetween('canonical_order', [$bookStart, $bookEnd])
            ->orderBy('canonical_order')
            ->get();

        $out = [];
        foreach ($books as $book) {
            $firstChapter = $book->id === $startBook->id ? (int) $block->start_chapter : 1;
            $lastChapter = $book->id === $endBook->id
                ? (int) ($block->end_chapter ?: $block->start_chapter)
                : (int) $book->chapter_count;

            for ($chapterNumber = $firstChapter; $chapterNumber <= $lastChapter; $chapterNumber++) {
                $chapter = BibleChapter::where('biblical_book_id', $book->id)
                    ->where('chapter_number', $chapterNumber)
                    ->first();

                if (! $chapter) {
                    continue;
                }

                $query = $chapter->versesForTranslation($translation->id);

                if ($book->id === $startBook->id && $chapterNumber === (int) $block->start_chapter && $block->start_verse) {
                    $query->where('verse_number', '>=', (int) $block->start_verse);
                }

                if ($book->id === $endBook->id && $chapterNumber === (int) ($block->end_chapter ?: $block->start_chapter) && $block->end_verse) {
                    $query->where('verse_number', '<=', (int) $block->end_verse);
                }

                foreach ($query->get(['verse_number', 'text']) as $verse) {
                    $text = $this->clean($verse->text);
                    if ($text === '') {
                        continue;
                    }

                    $out[] = [
                        'book' => $book->name_es ?: $book->osis_code,
                        'chapter' => $chapterNumber,
                        'verse' => (int) $verse->verse_number,
                        'text' => $text,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param array<int, array{book:string, chapter:int, verse:int, text:string}> $verses
     * @return array<int, string>
     */
    private function bodySegments(array $verses, int $maxChars): array
    {
        $segments = [];
        $current = '';
        $lastHeading = null;

        foreach ($verses as $verse) {
            $heading = $verse['book'].', capitulo '.$verse['chapter'].'.';
            $piece = ($heading !== $lastHeading ? $heading.' ' : '').$verse['text'];

            if ($current !== '' && mb_strlen($current.' '.$piece) > $maxChars) {
                $segments[] = trim($current);
                $current = '';
            }

            $current = trim($current === '' ? $piece : $current.' '.$piece);
            $lastHeading = $heading;
        }

        if ($current !== '') {
            $segments[] = trim($current);
        }

        return $segments;
    }

    private function prompt(string $voice): string
    {
        return "Lee en espanol latino como narrador masculino, natural, calido y reverente. "
            ."Haz pausas suaves entre escenas y capitulos. No digas numeros de versiculo. "
            ."No leas instrucciones. Narra exactamente el texto recibido con la voz {$voice}.";
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
