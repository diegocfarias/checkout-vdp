<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['term' => 'required|string|min:2|max:255']);

        $term = strtolower(trim($request->input('term')));
        $cacheKey = 'airports:' . md5($term);

        $results = Cache::remember($cacheKey, now()->addHours(24), function () use ($term) {
            $url = rtrim(config('services.vdp.url'), '/') . '/api/airports';

            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->post($url, ['term' => $term]);

                if ($response->failed()) {
                    Log::warning('AirportController: falha na API VDP', [
                        'term' => $term,
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                return $response->json() ?? [];
            } catch (\Throwable $e) {
                Log::error('AirportController: erro na requisição', [
                    'term' => $term,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });

        return response()->json($results);
    }
}
