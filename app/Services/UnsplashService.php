<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashService
{
    public function searchCityPhoto(string $city): ?array
    {
        $accessKey = config('services.unsplash.access_key');

        if (! $accessKey) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('https://api.unsplash.com/search/photos', [
                    'query' => $city . ' tourism landmark sightseeing',
                    'orientation' => 'landscape',
                    'per_page' => 1,
                    'client_id' => $accessKey,
                ]);

            if ($response->failed()) {
                Log::warning('Unsplash: falha na busca', [
                    'city' => $city,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            if (empty($results)) {
                return null;
            }

            $photo = $results[0];

            return [
                'url' => $photo['urls']['regular'] ?? $photo['urls']['small'] ?? null,
                'credit' => ($photo['user']['name'] ?? 'Unsplash') . ' / Unsplash',
            ];
        } catch (\Throwable $e) {
            Log::error('Unsplash: erro na requisição', [
                'city' => $city,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
