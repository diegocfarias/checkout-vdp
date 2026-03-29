<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashService
{
    public function searchCityPhoto(string $city, ?string $customQuery = null): ?array
    {
        $query = $customQuery ?: ($city . ' tourism landmark sightseeing');
        $photos = $this->searchPhotos($query, 1);

        return $photos[0] ?? null;
    }

    /**
     * Busca multiplas fotos no Unsplash. Resultados cacheados por 5 minutos.
     */
    public function searchPhotos(string $query, int $perPage = 6): array
    {
        $accessKey = config('services.unsplash.access_key');

        if (! $accessKey) {
            return [];
        }

        $cacheKey = 'unsplash:' . md5($query . ':' . $perPage);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $perPage, $accessKey) {
            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get('https://api.unsplash.com/search/photos', [
                        'query' => $query,
                        'orientation' => 'landscape',
                        'per_page' => $perPage,
                        'client_id' => $accessKey,
                    ]);

                if ($response->failed()) {
                    Log::warning('Unsplash: falha na busca', [
                        'query' => $query,
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                $data = $response->json();
                $results = $data['results'] ?? [];

                return collect($results)->map(fn ($photo) => [
                    'url' => $photo['urls']['regular'] ?? $photo['urls']['small'] ?? null,
                    'thumb' => $photo['urls']['small'] ?? $photo['urls']['thumb'] ?? null,
                    'credit' => ($photo['user']['name'] ?? 'Unsplash') . ' / Unsplash',
                ])->filter(fn ($p) => $p['url'])->values()->toArray();
            } catch (\Throwable $e) {
                Log::error('Unsplash: erro na requisição', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }
}
