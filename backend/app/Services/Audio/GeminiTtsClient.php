<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiTtsClient
{
    public function synthesizePcm(string $text, string $voice, string $model, string $prompt): string
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $prompt."\n\nTexto:\n".$text,
                ]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voice,
                        ],
                    ],
                ],
            ],
        ];

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($apiKey)
        );

        $response = Http::timeout((int) config('services.gemini.tts_timeout', 240))
            ->retry(2, 1500)
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini TTS failed with HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500));
        }

        $body = $response->json();
        $parts = data_get($body, 'candidates.0.content.parts', []);
        foreach ($parts as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (is_array($inline) && ! empty($inline['data'])) {
                $pcm = base64_decode((string) $inline['data'], true);
                if ($pcm === false || $pcm === '') {
                    break;
                }

                return $pcm;
            }
        }

        throw new RuntimeException('Gemini TTS response did not include inline audio data. '.$this->summarizeAudioMiss($body));
    }

    /**
     * @param array<string,mixed> $body
     */
    private function summarizeAudioMiss(array $body): string
    {
        $parts = data_get($body, 'candidates.0.content.parts', []);
        $partTypes = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                $partTypes[] = gettype($part);
                continue;
            }

            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (is_array($inline)) {
                $partTypes[] = 'inlineData:'.($inline['mimeType'] ?? $inline['mime_type'] ?? 'unknown');
                continue;
            }

            if (array_key_exists('text', $part)) {
                $partTypes[] = 'text:'.mb_strlen((string) $part['text']).' chars';
                continue;
            }

            $partTypes[] = 'keys:'.implode(',', array_keys($part));
        }

        return sprintf(
            'finish=%s block=%s parts=%s',
            data_get($body, 'candidates.0.finishReason', data_get($body, 'candidates.0.finish_reason', 'none')),
            data_get($body, 'promptFeedback.blockReason', data_get($body, 'prompt_feedback.block_reason', 'none')),
            $partTypes ? implode('|', $partTypes) : 'none'
        );
    }
}
