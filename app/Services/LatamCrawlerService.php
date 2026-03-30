<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LatamCrawlerService
{
    private function getTimeout(): int
    {
        return (int) Setting::get('crawler_timeout', 35);
    }

    /**
     * Busca voos LATAM via Crawler API e retorna no formato VDP.
     *
     * @param  array  $params  Formato VDP: departure, arrival, outbound_date, inbound_date, adults, children, infants, cabin
     * @return array{outbound: array, inbound: array}
     */
    public function searchFlights(array $params): array
    {
        $baseUrl = rtrim(config('services.latam_crawler.url', ''), '/');
        $apiKey = config('services.latam_crawler.api_key', '');

        if (empty($baseUrl) || empty($apiKey)) {
            Log::warning('LatamCrawler: URL ou API key não configurados');

            return ['outbound' => [], 'inbound' => []];
        }

        $cabin = $this->mapCabin($params['cabin'] ?? 'EC');
        $baseQuery = [
            'adt' => $params['adults'] ?? 1,
            'chd' => $params['children'] ?? 0,
            'inf' => $params['infants'] ?? 0,
            'cabin' => $cabin,
        ];

        $hasInbound = ! empty($params['inbound_date']);

        $timeout = $this->getTimeout();

        try {
            if ($hasInbound) {
                $responses = Http::pool(fn ($pool) => [
                    $pool->as('outbound')
                        ->withHeaders(['X-API-KEY' => $apiKey])
                        ->acceptJson()
                        ->timeout($timeout)
                        ->retry(2, 2000, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\ConnectionException || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429))
                        ->get("{$baseUrl}/v1/miles", array_merge($baseQuery, [
                            'ori' => $params['departure'] ?? '',
                            'dst' => $params['arrival'] ?? '',
                            'outbound_date' => $params['outbound_date'] ?? '',
                        ])),
                    $pool->as('inbound')
                        ->withHeaders(['X-API-KEY' => $apiKey])
                        ->acceptJson()
                        ->timeout($timeout)
                        ->retry(2, 2000, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\ConnectionException || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429))
                        ->get("{$baseUrl}/v1/miles", array_merge($baseQuery, [
                            'ori' => $params['arrival'] ?? '',
                            'dst' => $params['departure'] ?? '',
                            'outbound_date' => $params['inbound_date'],
                        ])),
                ]);

                $outbound = $this->transformResponse($responses['outbound'] ?? null, $params['cabin'] ?? 'EC');
                $inbound = $this->transformResponse($responses['inbound'] ?? null, $params['cabin'] ?? 'EC');
            } else {
                $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                    ->acceptJson()
                    ->timeout($timeout)
                    ->retry(2, 2000, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\ConnectionException || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429))
                    ->get("{$baseUrl}/v1/miles", array_merge($baseQuery, [
                        'ori' => $params['departure'] ?? '',
                        'dst' => $params['arrival'] ?? '',
                        'outbound_date' => $params['outbound_date'] ?? '',
                    ]));

                $outbound = $this->transformResponse($response, $params['cabin'] ?? 'EC');
                $inbound = [];
            }

            return ['outbound' => $outbound, 'inbound' => $inbound];
        } catch (\Throwable $e) {
            Log::warning('LatamCrawler: falha na busca', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return ['outbound' => [], 'inbound' => []];
        }
    }

    /**
     * Transforma a resposta da API Crawler em array de voos no formato VDP.
     */
    public function transformResponse($response, string $cabin): array
    {
        if (! $response || $response instanceof \Throwable) {
            Log::warning('LatamCrawler: resposta inválida ou erro na pool', [
                'error' => $response instanceof \Throwable ? $response->getMessage() : 'null response',
            ]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('LatamCrawler: API retornou erro', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json();
        $content = $data['content'] ?? [];
        $flights = [];
        $targetCabinId = $cabin === 'EX' ? 'C' : 'Y';

        foreach ($content as $item) {
            $flight = $this->transformFlight($item, $targetCabinId);
            if ($flight) {
                $flights[] = $flight;
            }
        }

        return $flights;
    }

    /**
     * Mapeia um item do content[] da API Crawler para o formato flat VDP.
     */
    private function transformFlight(array $item, string $targetCabinId): ?array
    {
        $summary = $item['summary'] ?? [];
        $brands = $summary['brands'] ?? [];

        $brand = $this->findCheapestBrand($brands, $targetCabinId);
        if (! $brand) {
            return null;
        }

        $origin = $summary['origin'] ?? [];
        $destination = $summary['destination'] ?? [];
        $itinerary = $item['itinerary'] ?? [];

        $connection = null;
        if (($summary['stopOvers'] ?? 0) > 0 && count($itinerary) > 1) {
            $connection = $this->buildConnection($itinerary);
        }

        return [
            'operator' => 'LATAM',
            'flight_number' => $summary['flightCode'] ?? '',
            'departure_time' => $this->padTime($origin['departureTime'] ?? ''),
            'arrival_time' => $this->padTime($destination['arrivalTime'] ?? ''),
            'departure_location' => $origin['iataCode'] ?? '',
            'arrival_location' => $destination['iataCode'] ?? '',
            'departure_label' => ($origin['city'] ?? '') . ' (' . ($origin['iataCode'] ?? '') . ')',
            'arrival_label' => ($destination['city'] ?? '') . ' (' . ($destination['iataCode'] ?? '') . ')',
            'boarding_tax' => number_format($brand['taxes']['amount'] ?? 0, 2, ',', '.'),
            'class_service' => $brand['cabin']['label'] ?? 'Economy',
            'price_money' => number_format($brand['priceWithOutTax']['amount'] ?? 0, 2, ',', '.'),
            'price_miles' => (string) ($brand['price']['amount'] ?? 0),
            'price_miles_vip' => null,
            'total_flight_duration' => $this->formatDuration($summary['duration'] ?? 0),
            'unique_id' => $brand['offerId'] ?? '',
            'connection' => $connection,
        ];
    }

    /**
     * Encontra o brand mais barato para o cabin desejado (Y = Economy, C = Business).
     */
    private function findCheapestBrand(array $brands, string $targetCabinId): ?array
    {
        $match = null;
        $minPrice = PHP_INT_MAX;

        foreach ($brands as $brand) {
            $cabinId = $brand['cabin']['id'] ?? '';
            if ($cabinId !== $targetCabinId) {
                continue;
            }

            $price = $brand['price']['amount'] ?? PHP_INT_MAX;
            if ($price < $minPrice) {
                $minPrice = $price;
                $match = $brand;
            }
        }

        return $match;
    }

    /**
     * Transforma itinerary[] em array de segments com chaves MAIUSCULAS
     * compatíveis com _connection_details.blade.php.
     */
    private function buildConnection(array $itinerary): array
    {
        $segments = [];

        foreach ($itinerary as $i => $seg) {
            $depTime = $this->extractTimeFromDatetime($seg['departure'] ?? '');
            $arrTime = $this->extractTimeFromDatetime($seg['arrival'] ?? '');

            $waitTime = '';
            if (isset($itinerary[$i + 1])) {
                $nextDep = $itinerary[$i + 1]['departure'] ?? '';
                $curArr = $seg['arrival'] ?? '';
                if ($nextDep && $curArr) {
                    $waitMinutes = (strtotime($nextDep) - strtotime($curArr)) / 60;
                    if ($waitMinutes > 0) {
                        $waitTime = $this->formatDuration((int) $waitMinutes);
                    }
                }
            }

            $flightNumber = 'LA' . ($seg['flight']['flightNumber'] ?? '');

            $segments[] = [
                'DEPARTURE_TIME' => $depTime,
                'ARRIVAL_TIME' => $arrTime,
                'DEPARTURE_LOCATION' => $seg['origin'] ?? '',
                'ARRIVAL_LOCATION' => $seg['destination'] ?? '',
                'FLIGHT_NUMBER' => $flightNumber,
                'FLIGHT_DURATION' => $this->formatDuration($seg['duration'] ?? 0),
                'OP' => 'LATAM',
                'TIME_WAITING' => $waitTime,
            ];
        }

        return $segments;
    }

    private function extractTimeFromDatetime(string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }

        $parts = explode('T', $datetime);
        if (count($parts) < 2) {
            return '';
        }

        $timeParts = explode(':', $parts[1]);

        return $this->padTime(($timeParts[0] ?? '00') . ':' . ($timeParts[1] ?? '00'));
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h 00';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours . 'h ' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT);
    }

    private function padTime(string $time): string
    {
        if (empty($time)) {
            return '';
        }

        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return $time;
        }

        return str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($parts[1], 2, '0', STR_PAD_LEFT);
    }

    /**
     * Mapeia cabin VDP (EC/EX) para cabin Crawler (0/1).
     */
    public function mapCabin(string $cabin): int
    {
        return strtoupper($cabin) === 'EX' ? 1 : 0;
    }
}
