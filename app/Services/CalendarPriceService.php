<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendarPriceService
{
    /**
     * Consulta a API externa de preços por data.
     *
     * @return array{levels: array<string, string>, currency: string|null, source: string}
     */
    public function datePrices(
        string $departure,
        string $arrival,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        $departure = strtoupper($departure);
        $arrival = strtoupper($arrival);
        $cacheKey = 'calendar_prices_123:'.md5(json_encode([
            $departure,
            $arrival,
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($departure, $arrival, $dateFrom, $dateTo) {
            return $this->requestDatePrices($departure, $arrival, $dateFrom, $dateTo);
        });
    }

    /**
     * @return array{levels: array<string, string>, currency: string|null, source: string}
     */
    private function requestDatePrices(
        string $departure,
        string $arrival,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        $url = config('services.calendar_prices_123.url', 'https://123milhas.com/api/flight/prices');
        $timeout = (int) config('services.calendar_prices_123.timeout', 8);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry(1, 200)
                ->post($url, [
                    'bounds' => [
                        [
                            'boundNumber' => 0,
                            'departureDate' => [
                                'start' => $dateFrom->format('Y-m-d'),
                                'end' => $dateTo->format('Y-m-d'),
                            ],
                            'origin' => [
                                'iatas' => [$departure],
                            ],
                            'destination' => [
                                'iatas' => [$arrival],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('123Milhas calendar prices failed', [
                    'status' => $response->status(),
                    'departure' => $departure,
                    'arrival' => $arrival,
                ]);

                return $this->emptyResult();
            }

            return $this->parseResponse((array) $response->json());
        } catch (\Throwable $e) {
            Log::warning('123Milhas calendar prices exception', [
                'departure' => $departure,
                'arrival' => $arrival,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyResult();
        }
    }

    /**
     * @return array{levels: array<string, string>, currency: string|null, source: string}
     */
    private function parseResponse(array $data): array
    {
        $prices = [];
        $levels = [];

        foreach ($data['offers'] ?? [] as $offer) {
            $price = $offer['totalPrice'] ?? null;
            if (! is_numeric($price) || (float) $price <= 0) {
                continue;
            }

            $dates = $offer['bounds'][0]['departureDates'] ?? [];
            foreach ((array) $dates as $date) {
                $dateKey = $this->dateKeyFromIso((string) $date);
                if ($dateKey === null) {
                    continue;
                }

                $normalizedPrice = round((float) $price, 2);
                if (! isset($prices[$dateKey]) || $normalizedPrice < $prices[$dateKey]) {
                    $prices[$dateKey] = $normalizedPrice;

                    $level = $this->normalizeRating($offer['rating'] ?? null);
                    if ($level !== null) {
                        $levels[$dateKey] = $level;
                    } else {
                        unset($levels[$dateKey]);
                    }
                }
            }
        }

        ksort($prices);
        ksort($levels);

        return [
            'levels' => $levels,
            'currency' => $data['currency'] ?? null,
            'source' => ! empty($prices) ? '123milhas' : 'cache',
        ];
    }

    private function dateKeyFromIso(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $matches)) {
            return null;
        }

        return $matches[0];
    }

    private function normalizeRating(mixed $rating): ?string
    {
        return match (strtoupper((string) $rating)) {
            'CHEAP', 'LOW' => 'low',
            'AVERAGE', 'MEDIUM' => 'medium',
            'EXPENSIVE', 'HIGH' => 'high',
            default => null,
        };
    }

    /**
     * @return array{levels: array<string, string>, currency: string|null, source: string}
     */
    private function emptyResult(): array
    {
        return [
            'levels' => [],
            'currency' => null,
            'source' => 'cache',
        ];
    }
}
