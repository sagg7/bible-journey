<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\EventDetailResource;
use App\Http\Resources\Api\EventSummaryResource;
use App\Models\HistoricalEvent;
use App\Support\BibleTranslationResolver;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function show(Request $request, HistoricalEvent $event)
    {
        // Gating premium: si el evento es premium y el usuario no lo es, devolver solo el resumen bloqueado.
        if ($event->is_premium && ! EventSummaryResource::userIsPremium($request)) {
            $event->load('translations');

            return response()->json([
                'data' => array_merge(
                    (new EventSummaryResource($event))->toArray($request),
                    ['locked' => true, 'message' => 'Este evento es premium.']
                ),
            ], 200);
        }

        $request->attributes->set('bibleTranslation', BibleTranslationResolver::forRequest($request));

        $event->load([
            'translations',
            'location.translations',
            'eventPassages' => fn ($q) => $q->with(['translations', 'passage.texts', 'passage.book']),
            'eventCharacters' => fn ($q) => $q->with('character.translations'),
            'psalmConnections' => fn ($q) => $q->with(['translations', 'passage.texts', 'passage.book']),
            'contextNotes.translations',
        ]);

        return new EventDetailResource($event);
    }
}
