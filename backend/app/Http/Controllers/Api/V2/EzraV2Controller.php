<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AiInteraction;
use App\Models\ChronologicalReadingSet;
use App\Models\StreamPlanNode;
use App\Services\Ezra\LlmClientInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class EzraV2Controller extends Controller
{
    public function __construct(private readonly LlmClientInterface $client) {}

    // POST /api/v2/ezra/answer
    // Body: { "question": "...", "node_id": 42, "plan_id": 1 }
    public function answer(Request $request): JsonResponse
    {
        if (! $this->client->configured()) {
            return response()->json([
                'error' => 'Ezra no disponible: falta EZRA_API_KEY.',
            ], 503);
        }

        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'node_id'  => 'nullable|integer|exists:stream_plan_nodes,id',
            'plan_id'  => 'nullable|integer|exists:stream_plans,id',
        ]);

        $user   = $request->user();
        $locale = $request->header('Accept-Language', app()->getLocale());
        $locale = str_starts_with($locale, 'en') ? 'en' : 'es';

        // Build CRS context if node_id given
        $crsContext = '';
        $node       = null;
        if (! empty($data['node_id'])) {
            $node = StreamPlanNode::with([
                'crs',
                'crs.blocks',
            ])->find($data['node_id']);

            if ($node?->crs) {
                if ($node->crs->is_premium && ! $user?->hasPremiumAccess()) {
                    return response()->json(['error' => 'Este contenido requiere suscripción.'], 403);
                }
                $crsContext = $this->buildCrsContext($node->crs, $locale);
            }
        }

        $cacheKey = 'ezra2:' . md5(
            ($data['node_id'] ?? 'none') . '|' . $locale . '|' . Str::lower(trim($data['question']))
        );

        if (config('ezra.cache_ttl', 0) > 0 && ($cached = Cache::get($cacheKey))) {
            return response()->json(['data' => $cached, 'cached' => true]);
        }

        $system   = $this->systemPrompt($locale, $user?->reading_level ?? 'adulto');
        $userMsg  = $crsContext
            ? "CONTEXTO DEL PASAJE ACTUAL:\n\n{$crsContext}\n\n---\n\nPREGUNTA: {$data['question']}"
            : "PREGUNTA: {$data['question']}";

        try {
            $result = $this->client->send($system, [
                ['role' => 'user', 'content' => $userMsg],
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Ezra no pudo responder en este momento.'], 502);
        }

        $structured = $this->parseStructured($result['text']);

        if (config('ezra.cache_ttl', 0) > 0) {
            Cache::put($cacheKey, $structured, config('ezra.cache_ttl'));
        }

        AiInteraction::create([
            'user_id'  => $user?->id,
            'historical_event_id' => $node?->crs?->historical_event_id,
            'locale'   => $locale,
            'question' => $data['question'],
            'answer'   => $result['text'],
            'model_used'         => $result['model'],
            'prompt_tokens'      => $result['input_tokens'],
            'completion_tokens'  => $result['output_tokens'],
            'token_cost'         => $this->computeCost($result['input_tokens'], $result['output_tokens']),
            'cache_hit'          => false,
        ]);

        return response()->json(['data' => $structured, 'cached' => false]);
    }

    private function buildCrsContext(ChronologicalReadingSet $crs, string $locale): string
    {
        $blocks = $crs->blocks->map(fn($b) =>
            "- [{$b->role}] {$b->display_reference}" . ($b->display_label_es ? " ({$b->display_label_es})" : '')
        )->implode("\n");

        return <<<CTX
            Pasaje: {$crs->title_es}
            Era: {$crs->era}
            Fuente: {$crs->source_map}
            Certeza de ubicación: {$crs->placement_confidence}
            Certeza del evento: {$crs->event_confidence}

            Bloques de lectura:
            {$blocks}

            Nota editorial: {$crs->editorial_note}
            CTX;
    }

    private function systemPrompt(string $locale, string $readingLevel): string
    {
        $lang = $locale === 'en' ? 'English' : 'español';

        return <<<PROMPT
            Eres **Ezra**, una guía de estudio bíblico cálida, clara y honesta.

            REGLAS:
            1. Responde ÚNICAMENTE con base en el CONTEXTO DEL PASAJE ACTUAL que se te proporciona.
               Si no puedes responder con certeza, dilo honestamente.
            2. RESPETA Y COMUNICA LOS NIVELES DE CERTEZA. Si algo está marcado como debatido,
               NO lo presentes como hecho.
            3. No inventes citas, fechas ni datos que no estén en el contexto.

            FORMATO DE RESPUESTA — devuelve SIEMPRE un JSON con esta estructura exacta:
            {
              "direct_answer": "Respuesta directa a la pregunta (1-3 oraciones)",
              "biblical_basis": {
                "reference": "Referencia bíblica principal",
                "quote": "Cita relevante (máx 50 palabras)"
              },
              "historical_context": "Contexto histórico relevante (1-2 párrafos)",
              "editorial_note": "Inferencia o interpretación editorial, etiquetada como tal",
              "certainty_level": "alta | probable | debatida | tradicion_popular | especulativa",
              "certainty_explanation": "Por qué este nivel de certeza",
              "sources": ["lista de fuentes o referencias utilizadas"],
              "reflection_question": "Pregunta de reflexión para el usuario"
            }

            Responde en {$lang}. Nivel de lectura: {$readingLevel}.
            PROMPT;
    }

    private function parseStructured(string $raw): array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback: return as direct_answer if LLM didn't return JSON
        return [
            'direct_answer'         => $raw,
            'biblical_basis'        => null,
            'historical_context'    => null,
            'editorial_note'        => null,
            'certainty_level'       => 'probable',
            'certainty_explanation' => null,
            'sources'               => [],
            'reflection_question'   => null,
        ];
    }

    private function computeCost(int $inTokens, int $outTokens): float
    {
        $pricing = config('ezra.pricing', ['input_per_mtok' => 0.27, 'output_per_mtok' => 0.27]);
        return round(
            ($inTokens / 1_000_000 * $pricing['input_per_mtok']) +
            ($outTokens / 1_000_000 * $pricing['output_per_mtok']),
            6
        );
    }
}
