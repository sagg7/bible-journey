<?php

namespace App\Services\Ezra;

use App\Models\AiInteraction;
use App\Models\HistoricalEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Ezra: guía de estudio basada en IA, anclada al contenido preconstruido del evento.
 */
class EzraService
{
    public function __construct(private readonly LlmClientInterface $client) {}

    public function isAvailable(): bool
    {
        return $this->client->configured();
    }

    /**
     * @return array{answer:string, cached:bool, model:?string, certainty_aware:bool}
     */
    public function ask(HistoricalEvent $event, string $question, string $locale, ?User $user = null): array
    {
        $readingLevel = $user?->reading_level ?? 'adulto';
        $cacheKey = 'ezra:'.md5($event->id.'|'.$locale.'|'.$readingLevel.'|'.Str::lower(trim($question)));

        if (config('ezra.cache_ttl') > 0 && ($cached = Cache::get($cacheKey))) {
            $this->log($user, $event, $question, $cached, $locale, null, 0, 0, true);

            return ['answer' => $cached, 'cached' => true, 'model' => null, 'certainty_aware' => true];
        }

        $context = EventContextBuilder::build($event, $locale);
        $system = $this->systemPrompt($locale, $readingLevel);

        $result = $this->client->send($system, [
            ['role' => 'user', 'content' => "CONTEXTO DEL EVENTO ACTUAL:\n\n{$context}\n\n---\n\nPREGUNTA DEL USUARIO:\n{$question}"],
        ]);

        $answer = trim($result['text']);

        if (config('ezra.cache_ttl') > 0) {
            Cache::put($cacheKey, $answer, config('ezra.cache_ttl'));
        }

        $this->log($user, $event, $question, $answer, $locale, $result['model'], $result['input_tokens'], $result['output_tokens'], false);

        return ['answer' => $answer, 'cached' => false, 'model' => $result['model'], 'certainty_aware' => true];
    }

    private function systemPrompt(string $locale, string $readingLevel): string
    {
        $lang = $locale === 'en' ? 'English' : 'español';

        return <<<PROMPT
            Eres **Ezra**, una guía de estudio bíblico cálida, clara y honesta. Acompañas al usuario
            mientras estudia la vida de David en orden cronológico.

            REGLAS ABSOLUTAS:
            1. Responde ÚNICAMENTE con base en el CONTEXTO DEL EVENTO ACTUAL que se te proporciona.
               Si la pregunta no puede responderse con ese contexto, di con honestidad:
               "No tengo suficiente información en esta ruta para responder eso con seguridad" y, si aplica,
               sugiere lo que sí puedes abordar.
            2. RESPETA Y COMUNICA LOS NIVELES DE CERTEZA. Si algo está marcado como "Debatida",
               "Tradición popular" o "Especulativa", NO lo presentes como hecho; explica que hay varias
               posturas. Distingue siempre entre lo que dice el texto bíblico y lo interpretativo.
            3. No seas dogmático en temas debatidos entre denominaciones. Presenta posturas con respeto.
            4. No inventes citas, fechas, lugares ni datos que no estén en el contexto.
            5. Lleva al usuario al texto bíblico; no lo reemplaces.

            ESTILO:
            - Responde en {$lang}.
            - Nivel de lectura: {$readingLevel} (ajusta vocabulario y profundidad).
            - Claro antes que erudito. Breve y cálido. Puedes terminar con una pregunta de reflexión.
            PROMPT;
    }

    private function log(?User $user, HistoricalEvent $event, string $question, string $answer, string $locale, ?string $model, int $in, int $out, bool $cacheHit): void
    {
        $pricing = config('ezra.pricing');
        $cost = ($in / 1_000_000 * $pricing['input_per_mtok']) + ($out / 1_000_000 * $pricing['output_per_mtok']);

        AiInteraction::create([
            'user_id' => $user?->id,
            'historical_event_id' => $event->id,
            'locale' => $locale,
            'question' => $question,
            'answer' => $answer,
            'model_used' => $model,
            'prompt_tokens' => $in,
            'completion_tokens' => $out,
            'token_cost' => round($cost, 6),
            'cache_hit' => $cacheHit,
        ]);
    }
}
