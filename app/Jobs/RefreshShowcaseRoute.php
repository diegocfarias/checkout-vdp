<?php

namespace App\Jobs;

use App\Models\ShowcaseRoute;
use App\Services\VdpFlightService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshShowcaseRoute implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public ShowcaseRoute $showcaseRoute,
    ) {}

    public function handle(VdpFlightService $vdpService): void
    {
        $route = $this->showcaseRoute;
        $dates = $route->sampleDates();
        $apiUrl = rtrim(config('services.vdp.url'), '/') . '/api/search/flights';

        $requestMap = [];
        foreach ($dates as $outboundDate) {
            $params = [
                'cia' => 'all',
                'departure' => strtoupper($route->departure_iata),
                'arrival' => strtoupper($route->arrival_iata),
                'outbound_date' => $outboundDate,
                'inbound_date' => null,
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'cabin' => $route->cabin,
            ];

            $returnDate = null;
            if ($route->trip_type === 'roundtrip' && $route->return_stay_days) {
                $returnDate = date('Y-m-d', strtotime($outboundDate . ' + ' . $route->return_stay_days . ' days'));
                $params['inbound_date'] = $returnDate;
            }

            $requestMap[] = [
                'params' => $params,
                'outbound_date' => $outboundDate,
                'return_date' => $returnDate,
            ];
        }

        Log::info('ShowcaseRoute: disparando ' . count($requestMap) . ' buscas em paralelo', [
            'route_id' => $route->id,
            'route' => $route->routeLabel(),
        ]);

        $responses = Http::pool(function ($pool) use ($requestMap, $apiUrl) {
            foreach ($requestMap as $i => $req) {
                $pool->as("date_{$i}")
                    ->acceptJson()
                    ->timeout(60)
                    ->post($apiUrl, $req['params']);
            }
        });

        $bestPrice = null;
        $bestDate = null;
        $bestReturnDate = null;
        $bestAirline = null;
        $bestFlightData = null;

        foreach ($requestMap as $i => $req) {
            $key = "date_{$i}";
            $response = $responses[$key] ?? null;

            if (! $response || $response->failed()) {
                Log::warning('ShowcaseRoute: falha na busca paralela', [
                    'route_id' => $route->id,
                    'date' => $req['outbound_date'],
                    'status' => $response?->status(),
                ]);

                continue;
            }

            try {
                $results = $response->json();
                $outboundFlights = $results['outbound'] ?? [];

                foreach ($outboundFlights as $flight) {
                    $price = $vdpService->calculateFlightPrice($flight);

                    if ($route->trip_type === 'roundtrip') {
                        $inboundFlights = $results['inbound'] ?? [];
                        if (empty($inboundFlights)) {
                            continue;
                        }

                        $cheapestInboundPrice = PHP_FLOAT_MAX;
                        foreach ($inboundFlights as $ibFlight) {
                            $ibPrice = $vdpService->calculateFlightPrice($ibFlight);
                            if ($ibPrice < $cheapestInboundPrice) {
                                $cheapestInboundPrice = $ibPrice;
                            }
                        }

                        if ($cheapestInboundPrice === PHP_FLOAT_MAX) {
                            continue;
                        }

                        $totalPrice = round($price + $cheapestInboundPrice, 2);
                    } else {
                        $totalPrice = round($price, 2);
                    }

                    if ($bestPrice === null || $totalPrice < $bestPrice) {
                        $bestPrice = $totalPrice;
                        $bestDate = $req['outbound_date'];
                        $bestReturnDate = $req['return_date'];
                        $bestAirline = $flight['operator'] ?? null;
                        $bestFlightData = [
                            'departure_time' => $flight['departure_time'] ?? null,
                            'arrival_time' => $flight['arrival_time'] ?? null,
                            'total_flight_duration' => $flight['total_flight_duration'] ?? null,
                            'connection_count' => is_array($flight['connection'] ?? null) ? max(0, count($flight['connection']) - 1) : 0,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ShowcaseRoute: falha ao processar resultado', [
                    'route_id' => $route->id,
                    'date' => $req['outbound_date'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $route->update([
            'cached_price' => $bestPrice,
            'cached_date' => $bestDate,
            'cached_return_date' => $bestReturnDate,
            'cached_airline' => $bestAirline,
            'cached_flight_data' => $bestFlightData,
            'last_refreshed_at' => now(),
        ]);

        Log::info('ShowcaseRoute: atualizada', [
            'route_id' => $route->id,
            'route' => $route->routeLabel(),
            'price' => $bestPrice,
            'date' => $bestDate,
        ]);
    }
}
