<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\LatamCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LatamCrawlerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    public function test_search_flights_returns_empty_when_config_is_missing(): void
    {
        config()->set('services.latam_crawler.url', '');
        config()->set('services.latam_crawler.api_key', '');
        Http::fake();

        $result = app(LatamCrawlerService::class)->searchFlights([
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
        ]);

        $this->assertSame(['outbound' => [], 'inbound' => []], $result);
        Http::assertNothingSent();
    }

    public function test_search_flights_transforms_oneway_response_with_cheapest_economy_brand(): void
    {
        config()->set('services.latam_crawler.url', 'https://latam.test/');
        config()->set('services.latam_crawler.api_key', 'latam-key');
        Setting::set('crawler_timeout', 9, 'integer');

        Http::fake([
            'https://latam.test/v1/miles*' => Http::response([
                'content' => [
                    $this->latamFlightItem(),
                ],
            ]),
        ]);

        $result = app(LatamCrawlerService::class)->searchFlights([
            'departure' => 'GRU',
            'arrival' => 'REC',
            'outbound_date' => '2026-06-10',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
        ]);

        $flight = $result['outbound'][0];
        $this->assertSame([], $result['inbound']);
        $this->assertSame('LATAM', $flight['operator']);
        $this->assertSame('LA3210', $flight['flight_number']);
        $this->assertSame('06:05', $flight['departure_time']);
        $this->assertSame('10:30', $flight['arrival_time']);
        $this->assertSame('79,90', $flight['boarding_tax']);
        $this->assertSame('350,00', $flight['price_money']);
        $this->assertSame('12000', $flight['price_miles']);
        $this->assertSame('2h 00', $flight['total_flight_duration']);
        $this->assertSame('offer-cheapest', $flight['unique_id']);

        Http::assertSent(function ($request): bool {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return str_starts_with($request->url(), 'https://latam.test/v1/miles?')
                && $request->hasHeader('X-API-KEY', 'latam-key')
                && $query['ori'] === 'GRU'
                && $query['dst'] === 'REC'
                && $query['outbound_date'] === '2026-06-10'
                && $query['adt'] === '1'
                && $query['cabin'] === '0';
        });
    }

    public function test_transform_response_builds_connection_segments_for_business_cabin(): void
    {
        $response = $this->httpResponse([
            'content' => [
                $this->latamFlightItem([
                    'stopOvers' => 1,
                    'brands' => [
                        [
                            'offerId' => 'business-offer',
                            'cabin' => ['id' => 'C', 'label' => 'Premium Business'],
                            'price' => ['amount' => 50000],
                            'priceWithOutTax' => ['amount' => 1200],
                            'taxes' => ['amount' => 150.45],
                        ],
                    ],
                ], [
                    [
                        'departure' => '2026-06-10T06:05:00',
                        'arrival' => '2026-06-10T08:00:00',
                        'origin' => 'GRU',
                        'destination' => 'BSB',
                        'duration' => 115,
                        'flight' => ['flightNumber' => '1234'],
                    ],
                    [
                        'departure' => '2026-06-10T09:30:00',
                        'arrival' => '2026-06-10T11:00:00',
                        'origin' => 'BSB',
                        'destination' => 'REC',
                        'duration' => 90,
                        'flight' => ['flightNumber' => '5678'],
                    ],
                ]),
            ],
        ]);

        $flights = app(LatamCrawlerService::class)->transformResponse($response, 'EX');

        $this->assertCount(1, $flights);
        $this->assertSame('Premium Business', $flights[0]['class_service']);
        $this->assertSame('150,45', $flights[0]['boarding_tax']);
        $this->assertCount(2, $flights[0]['connection']);
        $this->assertSame('LA1234', $flights[0]['connection'][0]['FLIGHT_NUMBER']);
        $this->assertSame('1h 30', $flights[0]['connection'][0]['TIME_WAITING']);
        $this->assertSame('LA5678', $flights[0]['connection'][1]['FLIGHT_NUMBER']);
    }

    public function test_transform_response_returns_empty_for_failed_or_invalid_responses(): void
    {
        $service = app(LatamCrawlerService::class);

        $this->assertSame([], $service->transformResponse(null, 'EC'));
        $this->assertSame([], $service->transformResponse($this->httpResponse(['error' => true], 500), 'EC'));
        $this->assertSame([], $service->transformResponse($this->httpResponse([
            'content' => [
                $this->latamFlightItem([
                    'brands' => [
                        [
                            'offerId' => 'business-only',
                            'cabin' => ['id' => 'C', 'label' => 'Business'],
                            'price' => ['amount' => 50000],
                            'priceWithOutTax' => ['amount' => 1200],
                            'taxes' => ['amount' => 150],
                        ],
                    ],
                ]),
            ],
        ]), 'EC'));
    }

    public function test_map_cabin_matches_vdp_codes(): void
    {
        $service = app(LatamCrawlerService::class);

        $this->assertSame(0, $service->mapCabin('EC'));
        $this->assertSame(1, $service->mapCabin('EX'));
        $this->assertSame(1, $service->mapCabin('ex'));
    }

    private function latamFlightItem(array $summaryOverrides = [], ?array $itinerary = null): array
    {
        return [
            'summary' => array_merge([
                'flightCode' => 'LA3210',
                'duration' => 120,
                'stopOvers' => 0,
                'origin' => [
                    'departureTime' => '6:5',
                    'iataCode' => 'GRU',
                    'city' => 'São Paulo',
                ],
                'destination' => [
                    'arrivalTime' => '10:30',
                    'iataCode' => 'REC',
                    'city' => 'Recife',
                ],
                'brands' => [
                    [
                        'offerId' => 'offer-expensive',
                        'cabin' => ['id' => 'Y', 'label' => 'Economy'],
                        'price' => ['amount' => 18000],
                        'priceWithOutTax' => ['amount' => 500],
                        'taxes' => ['amount' => 90],
                    ],
                    [
                        'offerId' => 'offer-cheapest',
                        'cabin' => ['id' => 'Y', 'label' => 'Economy'],
                        'price' => ['amount' => 12000],
                        'priceWithOutTax' => ['amount' => 350],
                        'taxes' => ['amount' => 79.9],
                    ],
                    [
                        'offerId' => 'offer-business',
                        'cabin' => ['id' => 'C', 'label' => 'Business'],
                        'price' => ['amount' => 60000],
                        'priceWithOutTax' => ['amount' => 1600],
                        'taxes' => ['amount' => 200],
                    ],
                ],
            ], $summaryOverrides),
            'itinerary' => $itinerary ?? [
                [
                    'departure' => '2026-06-10T06:05:00',
                    'arrival' => '2026-06-10T08:05:00',
                    'origin' => 'GRU',
                    'destination' => 'REC',
                    'duration' => 120,
                    'flight' => ['flightNumber' => '3210'],
                ],
            ],
        ];
    }

    private function httpResponse(array $body, int $status = 200): \Illuminate\Http\Client\Response
    {
        return new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode($body, JSON_THROW_ON_ERROR),
            ),
        );
    }
}
