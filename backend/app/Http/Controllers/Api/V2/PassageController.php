<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ReadingBlock;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassageController extends Controller
{
    // GET /api/v2/passages/block/{blockId}?translation=WEB
    public function showForBlock(Request $request, int $blockId): JsonResponse
    {
        $block = ReadingBlock::with('passage.texts.translation')->findOrFail($blockId);

        if (! $block->passage) {
            return response()->json([
                'has_text'  => false,
                'reference' => $block->display_reference,
                'verses'    => [],
            ]);
        }

        $code = $request->query('translation', 'WEB');
        $translation = Translation::where('code', $code)->first()
            ?? Translation::where('can_display_full_text', true)->first();

        if (! $translation) {
            return response()->json([
                'has_text'  => false,
                'reference' => $block->display_reference,
                'verses'    => [],
                'reason'    => 'no_translation',
            ]);
        }

        $passageText = $block->passage->textFor($translation);

        if (! $passageText) {
            return response()->json([
                'has_text'    => false,
                'reference'   => $block->display_reference,
                'translation' => $translation->code,
                'verses'      => [],
                'reason'      => 'no_text',
            ]);
        }

        return response()->json([
            'has_text'    => true,
            'reference'   => $block->display_reference,
            'book'        => $block->book,
            'translation' => $translation->code,
            'translation_name' => $translation->name,
            'verses'      => $passageText->verses ?? [],
        ]);
    }
}
