<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\ShowcaseRefreshLog;
use App\Models\ShowcaseRoute;
use App\Services\VdpFlightService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshShowcaseRoute implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public ShowcaseRoute $showcaseRoute,
    ) {}

    public function handle(VdpFlightService $vdpService): void
    {
        $route = $this->showcaseRoute;
        $dates = $route->sampleDates();
        $waitSeconds = max(1, (int) Setting::get('showcase_wait_seconds', 10));
        $startTime = now();

        $log = ShowcaseRefreshLog::create([
            'showcase_route_id' => $route->id,
            'status' => 'running',
            'previous_price' => $route->cached_price,
            'started_at' => $startTime,
        ]);

        $cacheHits = 0;
        $apiCalls = 0;
        $errorsCount = 0;
        $datesSearched = 0;

        Log::info('ShowcaseRoute: iniciando refresh sequencial', [
            'route_id' => $route->id,
            'log_id' => $log->id,
            'route' => $route->routeLabel(),
            'dates_count' => count($dates),
            'wait_seconds' => $waitSeconds,
        ]);

        $bestPrice = null;
        $bestDate = null;
        $bestReturnDate = null;
        $bestAirline = null;
        $bestFlightData = null;

        try {
            foreach ($dates as $index => $outboundDate) {
                $returnDate = null;
                if ($route->trip_type === 'roundtrip' && $route->return_stay_days) {
                    $returnDate = date('Y-m-d', strtotime($outboundDate . ' + ' . $route->return_stay_days . ' days'));
                }

                $params = [
                    'cia' => 'all',
                    'departure' => strtoupper($route->departure_iata),
                    'arrival' => strtoupper($route->arrival_iata),
                    'outbound_date' => $outboundDate,
                    'inbound_date' => $returnDate,
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                    'cabin' => $route->cabin,
                ];

                try {
                    $result = $vdpService->searchFlightsWithCacheInfo($params);
                    $fromCache = $result['from_cache'];
                    $results = $result['data'];
                    $datesSearched++;

                    if ($fromCache) {
                        $cacheHits++;
                    } else {
                        $apiCalls++;
                    }

                    Log::info('ShowcaseRoute: busca concluída', [
                        'route_id' => $route->id,
                        'date' => $outboundDate,
                        'from_cache' => $fromCache,
                        'index' => $index + 1,
                        'total' => count($dates),
                    ]);

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
                            $bestDate = $outboundDate;
                            $bestReturnDate = $returnDate;
                            $bestAirline = $flight['operator'] ?? null;
                            $bestFlightData = [
                                'departure_time' => $flight['departure_time'] ?? null,
                                'arrival_time' => $flight['arrival_time'] ?? null,
                                'total_flight_duration' => $flight['total_flight_duration'] ?? null,
                                'connection_count' => is_array($flight['connection'] ?? null) ? max(0, count($flight['connection']) - 1) : 0,
                            ];
                        }
                    }

                    if (! $fromCache) {
                        sleep($waitSeconds);
                    }
                } catch (\Throwable $e) {
                    $errorsCount++;
                    $datesSearched++;

                    Log::warning('ShowcaseRoute: falha na busca', [
                        'route_id' => $route->id,
                        'date' => $outboundDate,
                        'error' => $e->getMessage(),
                    ]);

                    sleep($waitSeconds);
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

            $finishedAt = now();
            $log->update([
                'status' => 'completed',
                'dates_searched' => $datesSearched,
                'cache_hits' => $cacheHits,
                'api_calls' => $apiCalls,
                'errors_count' => $errorsCount,
                'best_price' => $bestPrice,
                'best_date' => $bestDate,
                'duration_seconds' => $startTime->diffInSeconds($finishedAt),
                'finished_at' => $finishedAt,
            ]);

            Log::info('ShowcaseRoute: atualizada', [
                'route_id' => $route->id,
                'route' => $route->routeLabel(),
                'price' => $bestPrice,
                'date' => $bestDate,
                'cache_hits' => $cacheHits,
                'api_calls' => $apiCalls,
            ]);
        } catch (\Throwable $e) {
            $finishedAt = now();
            $log->update([
                'status' => 'failed',
                'dates_searched' => $datesSearched,
                'cache_hits' => $cacheHits,
                'api_calls' => $apiCalls,
                'errors_count' => $errorsCount + 1,
                'error_message' => $e->getMessage(),
                'duration_seconds' => $startTime->diffInSeconds($finishedAt),
                'finished_at' => $finishedAt,
            ]);

            throw $e;
        }
    }
}
