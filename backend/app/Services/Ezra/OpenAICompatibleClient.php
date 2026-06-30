<?php

namespace App\Services\Ezra;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente para APIs compatibles con OpenAI: Groq, OpenRouter, Cerebras, etc.
 * Configurar con EZRA_BASE_URL y EZRA_API_KEY en .env.
 */
class OpenAICompatibleClient implements LlmClientInterface
{
    public function configured(): bool
    {
        return ! empty(config('ezra.api_key'));
    }

    public function send(string $system, array $messages, ?string $model = null): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('EZRA_API_KEY no está configurada.');
        }

        $model ??= config('ezra.model');
        $baseUrl = rtrim(config('ezra.base_url'), '/');

        $allMessages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $messages
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('ezra.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(60)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'max_tokens' => config('ezra.max_tokens'),
                'messages' => $allMessages,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Error del proveedor de IA: '.$response->status().' '.$response->body());
        }

        $data = $response->json();

        return [
            'text' => (string) ($data['choices'][0]['message']['content'] ?? ''),
            'model' => $data['model'] ?? $model,
            'input_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
        ];
    }
}
