<?php

namespace App\Services\Ezra;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente para la Messages API de Anthropic (Claude).
 * Usar EZRA_PROVIDER=anthropic en .env para activar este cliente.
 */
class AnthropicClient implements LlmClientInterface
{
    public function configured(): bool
    {
        return ! empty(config('ezra.api_key'));
    }

    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{text:string, model:string, input_tokens:int, output_tokens:int}
     */
    public function send(string $system, array $messages, ?string $model = null): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('EZRA_API_KEY no está configurada.');
        }

        $model ??= config('ezra.model');

        $response = Http::withHeaders([
            'x-api-key' => config('ezra.api_key'),
            'anthropic-version' => config('ezra.anthropic_version'),
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(rtrim(config('ezra.base_url'), '/').'/v1/messages', [
                'model' => $model,
                'max_tokens' => config('ezra.max_tokens'),
                'system' => $system,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Error del proveedor de IA: '.$response->status().' '.$response->body());
        }

        $data = $response->json();

        return [
            'text' => collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n"),
            'model' => $data['model'] ?? $model,
            'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
        ];
    }
}
