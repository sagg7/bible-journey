<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el idioma de la respuesta a partir de (en orden de prioridad):
 *   1. ?locale=es|en
 *   2. cabecera X-Locale
 *   3. cabecera Accept-Language
 * Por defecto: es. Solo se aceptan los idiomas soportados.
 */
class SetLocale
{
    private const SUPPORTED = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('locale')
            ?? $request->header('X-Locale')
            ?? substr((string) $request->header('Accept-Language'), 0, 2);

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale', 'es');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
