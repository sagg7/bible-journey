<?php

return [
    /*
     * Proveedor de IA para Ezra (server-side, la app móvil nunca habla directo).
     *
     * 'openai_compatible' → Groq, OpenRouter, Cerebras, Together, etc.
     * 'anthropic'         → Anthropic Claude directo.
     *
     * Para cambiar de proveedor basta con ajustar las variables de .env:
     *   EZRA_PROVIDER, EZRA_BASE_URL, EZRA_API_KEY, EZRA_MODEL
     */
    'provider' => env('EZRA_PROVIDER', 'openai_compatible'),

    'api_key'  => env('EZRA_API_KEY'),
    'base_url' => env('EZRA_BASE_URL', 'https://api.groq.com/openai/v1'),

    // Solo usado por el cliente Anthropic
    'anthropic_version' => '2023-06-01',

    'model'          => env('EZRA_MODEL', 'llama-3.3-70b-versatile'),
    'model_fallback' => env('EZRA_MODEL_FALLBACK', 'llama-3.1-8b-instant'),
    'max_tokens'     => (int) env('EZRA_MAX_TOKENS', 1024),

    // Cache de respuestas frecuentes (segundos). 0 = desactivado.
    'cache_ttl' => (int) env('EZRA_CACHE_TTL', 60 * 60 * 24 * 30),

    // Costo estimado por millón de tokens (USD). Usar 0.0 para APIs gratuitas.
    'pricing' => [
        'input_per_mtok'  => (float) env('EZRA_PRICE_INPUT', 0.0),
        'output_per_mtok' => (float) env('EZRA_PRICE_OUTPUT', 0.0),
    ],
];
