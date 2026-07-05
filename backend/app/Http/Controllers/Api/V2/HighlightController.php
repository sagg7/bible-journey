<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BiblicalBook;
use App\Models\HighlightColor;
use App\Models\VerseHighlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HighlightController extends Controller
{
    // GET /api/v2/highlights?book=GEN&chapter=1&color_id=3
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = VerseHighlight::with(['color', 'book'])
            ->where('user_id', $user->id);

        if ($request->filled('book')) {
            $book = BiblicalBook::where('osis_code', strtoupper($request->string('book')))->firstOrFail();
            $query->where('book_id', $book->id);

            if ($request->filled('chapter')) {
                $query->where('chapter_number', $request->integer('chapter'));
            }
        }

        if ($request->filled('color_id')) {
            $query->where('highlight_color_id', $request->integer('color_id'));
        }

        $highlights = $query->orderBy('book_id')->orderBy('chapter_number')->orderBy('verse_start')->get();

        return response()->json(['data' => $highlights->map(fn ($h) => $this->present($h))]);
    }

    // POST /api/v2/highlights
    // Body: { book: "GEN", chapter: 1, verse_start: 1, verse_end: 3, color_hex: "#FFEB3B", label?: "Promesas" }
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'book'        => 'required|string',
            'chapter'     => 'required|integer|min:1',
            'verse_start' => 'required|integer|min:1',
            'verse_end'   => 'required|integer|min:1|gte:verse_start',
            'color_hex'   => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'label'       => 'nullable|string|max:60',
        ]);

        $user = $request->user();
        $book = BiblicalBook::where('osis_code', strtoupper($request->string('book')))->firstOrFail();

        $highlight = DB::transaction(function () use ($request, $user, $book) {
            $color = HighlightColor::updateOrCreate(
                ['user_id' => $user->id, 'color_hex' => strtoupper($request->string('color_hex'))],
                array_filter(['label' => $request->input('label')], fn ($v) => $v !== null)
            );

            return VerseHighlight::updateOrCreate(
                [
                    'user_id'        => $user->id,
                    'book_id'        => $book->id,
                    'chapter_number' => $request->integer('chapter'),
                    'verse_start'    => $request->integer('verse_start'),
                    'verse_end'      => $request->integer('verse_end'),
                ],
                ['highlight_color_id' => $color->id]
            );
        });

        $highlight->load(['color', 'book']);

        return response()->json(['data' => $this->present($highlight)], 201);
    }

    // DELETE /api/v2/highlights/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        $highlight = VerseHighlight::where('user_id', $request->user()->id)->findOrFail($id);
        $highlight->delete();

        return response()->json(['ok' => true]);
    }

    // GET /api/v2/highlight-colors
    public function colors(Request $request): JsonResponse
    {
        $colors = HighlightColor::withCount('verseHighlights')
            ->where('user_id', $request->user()->id)
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $colors->map(fn ($c) => [
            'id'    => $c->id,
            'hex'   => $c->color_hex,
            'label' => $c->label,
            'count' => $c->verse_highlights_count,
        ])]);
    }

    // PATCH /api/v2/highlight-colors/{id}
    // Body: { label: "Promesas de Dios" }
    public function updateColor(Request $request, string $id): JsonResponse
    {
        $request->validate(['label' => 'required|string|max:60']);

        $color = HighlightColor::where('user_id', $request->user()->id)->findOrFail($id);
        $color->update(['label' => $request->string('label')]);

        return response()->json(['data' => [
            'id' => $color->id, 'hex' => $color->color_hex, 'label' => $color->label,
        ]]);
    }

    private function present(VerseHighlight $h): array
    {
        return [
            'id'          => $h->id,
            'chapter'     => $h->chapter_number,
            'verse_start' => $h->verse_start,
            'verse_end'   => $h->verse_end,
            'book'        => [
                'osis_code' => $h->book->osis_code,
                'name_es'   => $h->book->name_es,
            ],
            'color' => [
                'id'    => $h->color->id,
                'hex'   => $h->color->color_hex,
                'label' => $h->color->label,
            ],
        ];
    }
}
