<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BdsCrawlerService
{
    private function getTimeout(): int
    {
        return (int) Setting::get('bds_crawler_timeout', 60);
    }

    /**
     * Busca voos via BDS Crawler API.
     * O crawler ja retorna no formato VDP flat padronizado.
     *
     * @param  array  $params  departure, arrival, outbound_date, inbound_date, adults, children, infants, cabin
     * @param  array|null  $airlines  Lista de cias para buscar (ex: ['GOL','LATAM']). Null = todas.
     * @return array{outbound: array, inbound: array}
     */
    public function searchFlights(array $params, ?array $airlines = null): array
    {
        $baseUrl = rtrim(config('services.bds_crawler.url', ''), '/');
        $apiKey = config('services.bds_crawler.api_key', '');

        if (empty($baseUrl)) {
            Log::warning('BdsCrawler: URL nao configurada');
            return ['outbound' => [], 'inbound' => []];
        }

        $query = [
            'origin' => $params['departure'] ?? '',
            'destination' => $params['arrival'] ?? '',
            'outbound_date' => $params['outbound_date'] ?? '',
            'adults' => $params['adults'] ?? 1,
            'children' => $params['children'] ?? 0,
            'infants' => $params['infants'] ?? 0,
            'cabin' => $params['cabin'] ?? 'EC',
        ];

        if (! empty($params['inbound_date'])) {
            $query['inbound_date'] = $params['inbound_date'];
        }

        if ($airlines) {
            $query['airlines'] = implode(',', array_map('strtoupper', $airlines));
        }

        $timeout = $this->getTimeout();

        try {
            $headers = ['Accept' => 'application/json'];
            if (! empty($apiKey)) {
                $headers['X-API-KEY'] = $apiKey;
            }

            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->retry(2, 2000, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429))
                ->get("{$baseUrl}/api/search", $query);

            if ($response->failed()) {
                Log::warning('BdsCrawler: API retornou erro', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['outbound' => [], 'inbound' => []];
            }

            $data = $response->json();

            return [
                'outbound' => $data['outbound'] ?? [],
                'inbound' => $data['inbound'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('BdsCrawler: falha na busca', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            return ['outbound' => [], 'inbound' => []];
        }
    }
}
