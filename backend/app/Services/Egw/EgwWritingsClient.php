<?php

namespace App\Services\Egw;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente OAuth2 client_credentials para la API de EGW Writings
 * (https://a.egwwritings.org). Usada para el feature "Espiritu de Profecia":
 * extractos citados de la serie El Conflicto de los Siglos por capitulo biblico.
 */
class EgwWritingsClient
{
    public function configured(): bool
    {
        return ! empty(config('services.egw.client_id')) && ! empty(config('services.egw.client_secret'));
    }

    protected function accessToken(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('EGW_CLIENT_ID / EGW_CLIENT_SECRET no estan configurados.');
        }

        return Cache::remember('egw_access_token', now()->addDays(10), function () {
            $response = Http::asForm()->post(config('services.egw.token_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.egw.client_id'),
                'client_secret' => config('services.egw.client_secret'),
                'scope' => 'writings search',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('No se pudo obtener token de EGW Writings: '.$response->status().' '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Busca un termino dentro de un tomo especifico (pubnr) de EGW Writings.
     *
     * @return array<int, array{para_id:string, refcode_short:string, refcode_long:string, snippet:string, pub_name:string, weight:float}>
     */
    public function searchBook(string $lang, int $pubnr, string $query, int $limit = 4): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(20)
            ->get(rtrim(config('services.egw.api_base'), '/').'/search/advanced/book', [
                'lang' => $lang,
                'pubnr' => $pubnr,
                'query' => $query,
                'limit' => $limit,
                'snippet' => 'full',
                'order' => 'rel',
            ]);

        if ($response->status() === 401) {
            // Token vencido/invalidado del lado del servidor: forzar renovacion y reintentar una vez.
            Cache::forget('egw_access_token');

            $response = Http::withToken($this->accessToken())
                ->timeout(20)
                ->get(rtrim(config('services.egw.api_base'), '/').'/search/advanced/book', [
                    'lang' => $lang,
                    'pubnr' => $pubnr,
                    'query' => $query,
                    'limit' => $limit,
                    'snippet' => 'full',
                    'order' => 'rel',
                ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Error de busqueda EGW Writings: '.$response->status().' '.$response->body());
        }

        return $response->json('results') ?? [];
    }
}
