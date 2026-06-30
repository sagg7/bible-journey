<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\EventSummaryResource;
use App\Models\HistoricalEvent;
use App\Services\Ezra\EzraService;
use Illuminate\Http\Request;
use Throwable;

class EzraController extends Controller
{
    public function __construct(private readonly EzraService $ezra) {}

    public function ask(Request $request, HistoricalEvent $event)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        // Ezra está acotado a eventos accesibles (respeta el gating premium).
        if ($event->is_premium && ! EventSummaryResource::userIsPremium($request)) {
            return response()->json(['message' => 'Este evento es premium.'], 403);
        }

        if (! $this->ezra->isAvailable()) {
            return response()->json([
                'message' => 'Ezra no está disponible: falta configurar EZRA_API_KEY en el servidor.',
            ], 503);
        }

        try {
            $result = $this->ezra->ask($event, $data['question'], app()->getLocale(), $request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Ezra no pudo responder en este momento.'], 502);
        }

        return response()->json(['data' => $result]);
    }
}
