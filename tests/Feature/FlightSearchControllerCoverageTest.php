<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FlightSearchControllerCoverageTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_date_prices_clamps_long_ranges_and_builds_roundtrip_cache_params(): void
    {
        Http::fake([
            'https://123milhas.com/api/flight/prices' => Http::response(['currency' => 'BRL', 'offers' => []]),
        ]);

        $firstDate = '2026-06-01';
        $lastAllowedDate = Carbon::parse($firstDate)->addDays(335)->format('Y-m-d');
        $blockedDate = Carbon::parse($firstDate)->addDays(336)->format('Y-m-d');

        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('getMinPriceFromCache')
            ->times(336)
            ->with(Mockery::on(fn (array $params): bool => $params['departure'] === 'GRU'
                && $params['arrival'] === 'SDU'
                && $params['inbound_date'] === Carbon::parse($params['outbound_date'])->addDays(2)->format('Y-m-d')))
            ->andReturn(321.45);
        $this->app->instance(VdpFlightService::class, $vdp);

        $response = $this->getJson(route('api.date-prices').'?'.http_build_query([
            'departure' => 'gru',
            'arrival' => 'sdu',
            'cabin' => 'EC',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'date_from' => $firstDate,
            'date_to' => '2028-01-01',
            'trip_type' => 'roundtrip',
            'inbound_offset' => 2,
        ]))->assertOk();

        $prices = $response->json('prices');

        $this->assertCount(336, $prices);
        $this->assertSame(321.45, $prices[$firstDate]);
        $this->assertSame(321.45, $prices[$lastAllowedDate]);
        $this->assertArrayNotHasKey($blockedDate, $prices);
    }

    public function test_date_prices_uses_external_calendar_prices_and_levels(): void
    {
        Http::fake([
            'https://123milhas.com/api/flight/prices' => Http::response([
                'currency' => 'BRL',
                'offers' => [
                    [
                        'bounds' => [[
                            'departureDates' => ['2026-07-16T07:50:00Z'],
                        ]],
                        'totalPrice' => 236.56,
                        'rating' => 'CHEAP',
                    ],
                    [
                        'bounds' => [[
                            'departureDates' => ['2026-07-17T15:40:00Z'],
                        ]],
                        'totalPrice' => 336.15,
                        'rating' => 'AVERAGE',
                    ],
                    [
                        'bounds' => [[
                            'departureDates' => ['2026-07-18T15:35:00Z'],
                        ]],
                        'totalPrice' => 700.15,
                        'rating' => 'EXPENSIVE',
                    ],
                    [
                        'bounds' => [[
                            'departureDates' => ['2026-07-18T18:35:00Z'],
                        ]],
                        'totalPrice' => 680.15,
                        'rating' => 'AVERAGE',
                    ],
                ],
            ]),
        ]);

        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('getMinPriceFromCache')
            ->times(3)
            ->andReturn(null);
        $this->app->instance(VdpFlightService::class, $vdp);

        $response = $this->getJson(route('api.date-prices').'?'.http_build_query([
            'departure' => 'bhz',
            'arrival' => 'gru',
            'cabin' => 'EC',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'date_from' => '2026-07-16',
            'date_to' => '2026-07-18',
            'trip_type' => 'oneway',
            'inbound_offset' => 0,
        ]))->assertOk();

        $this->assertSame(236.56, $response->json('prices.2026-07-16'));
        $this->assertSame(336.15, $response->json('prices.2026-07-17'));
        $this->assertSame(680.15, $response->json('prices.2026-07-18'));
        $this->assertSame('low', $response->json('levels.2026-07-16'));
        $this->assertSame('medium', $response->json('levels.2026-07-17'));
        $this->assertSame('medium', $response->json('levels.2026-07-18'));
        $this->assertSame('123milhas', $response->json('source'));

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://123milhas.com/api/flight/prices'
                && $data['bounds'][0]['origin']['iatas'] === ['BHZ']
                && $data['bounds'][0]['destination']['iatas'] === ['GRU']
                && $data['bounds'][0]['departureDate']['start'] === '2026-07-16'
                && $data['bounds'][0]['departureDate']['end'] === '2026-07-18';
        });
    }

    public function test_provider_endpoint_handles_invalid_failing_and_patria_slots(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class)->makePartial();
        $vdp->shouldReceive('searchSingleProvider')
            ->once()
            ->with(Mockery::type('array'), 'latam_crawler', 'LATAM')
            ->andThrow(new \RuntimeException('crawler fora'));
        $vdp->shouldReceive('searchSingleProvider')
            ->once()
            ->with(Mockery::type('array'), 'bds_crawler', 'PATRIA')
            ->andReturn([
                'outbound' => [
                    $this->flightPayload([
                        'operator' => 'PATRIA',
                        'flight_number' => 'LA3456',
                        'price_money' => '400,00',
                        'price_miles' => '10000',
                        'unique_id' => 'patria-outbound',
                        'connection' => [
                            [
                                'DEPARTURE_TIME' => '10:00',
                                'ARRIVAL_TIME' => '11:00',
                                'DEPARTURE_LOCATION' => 'GRU',
                                'ARRIVAL_LOCATION' => 'SDU',
                                'FLIGHT_NUMBER' => 'LA3456',
                                'EXTRA' => 'remove',
                            ],
                        ],
                        'raw_payload' => ['remove' => true],
                    ]),
                ],
                'inbound' => [],
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->getJson(route('api.search.provider', $this->providerQuery([
            'slot' => encrypt('unknown|GOL'),
        ])))
            ->assertStatus(400)
            ->assertExactJson(['outbound' => [], 'inbound' => []]);

        $this->getJson(route('api.search.provider', $this->providerQuery([
            'slot' => encrypt('latam_crawler|LATAM'),
        ])))
            ->assertOk()
            ->assertExactJson([
                'outbound' => [],
                'inbound' => [],
                'provider' => 'latam_crawler',
                'airlines' => 'LATAM',
            ]);

        $response = $this->getJson(route('api.search.provider', $this->providerQuery([
            'slot' => encrypt('bds_crawler|PATRIA'),
        ])))->assertOk();

        $flight = $response->json('outbound.0');

        $this->assertSame('patria-outbound', $flight['unique_id']);
        $this->assertSame('BDS Convencional', $flight['_provider']);
        $this->assertSame('convencional', $flight['_pricing_type']);
        $this->assertSame('bds_crawler', $flight['_source_provider']);
        $this->assertSame('PATRIA', $flight['_source_airlines']);
        $this->assertArrayNotHasKey('raw_payload', $flight);
        $this->assertArrayNotHasKey('EXTRA', $flight['connection'][0]);
        $this->assertGreaterThan(0, $flight['calculated_price']);
    }

    public function test_unconfirmed_selection_handles_unavailable_and_changed_prices(): void
    {
        $search = $this->createFlightSearch([
            'trip_type' => 'oneway',
        ]);

        $vdp = Mockery::mock(VdpFlightService::class)->makePartial();
        $vdp->shouldReceive('revalidateFlightPair')
            ->twice()
            ->andReturn(
                ['outbound' => null, 'inbound' => null],
                [
                    'outbound' => $this->flightPayload([
                        'price_money' => '150,00',
                        'unique_id' => 'changed-flight',
                    ]),
                    'inbound' => null,
                ],
            );
        $vdp->shouldReceive('forgetSearchCaches')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => $params['departure'] === 'GRU'
                && $params['arrival'] === 'SDU'
                && $params['outbound_date'] === '2026-06-10'));
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->from(route('search.results', $this->searchQuery()))
            ->post(route('search.select'), [
                'search_id' => $search->id,
                'outbound' => json_encode($this->flightPayload([
                    'unique_id' => 'gone-flight',
                ])),
            ])
            ->assertRedirect(route('search.results', $this->searchQuery()))
            ->assertSessionHas('search_refresh_modal.message', 'O voo de ida selecionado não está mais disponível.');

        $this->post(route('search.select'), [
            'search_id' => $search->id,
            'outbound' => json_encode($this->flightPayload([
                'price_money' => '100,00',
                'unique_id' => 'changed-flight',
            ])),
            'ob_provider' => 'VDP',
            'ob_pricing_type' => 'convencional',
        ])
            ->assertOk()
            ->assertViewIs('search.price-changed')
            ->assertViewHas('oldTotal', 130.0)
            ->assertViewHas('newTotal', 180.0)
            ->assertViewHas('diff', 50.0);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirmed_roundtrip_selection_maps_patria_airlines_from_flight_numbers(): void
    {
        $search = $this->createFlightSearch([
            'trip_type' => 'roundtrip',
            'inbound_date' => '2026-06-15',
        ]);

        $this->post(route('search.select'), [
            'search_id' => $search->id,
            'outbound' => json_encode($this->flightPayload([
                'operator' => 'PATRIA',
                'flight_number' => 'G31234',
                'unique_id' => 'patria-gol',
            ])),
            'inbound' => json_encode($this->flightPayload([
                'operator' => 'PATRIA',
                'flight_number' => 'AD4321',
                'unique_id' => 'patria-azul',
            ])),
            'confirmed' => '1',
            'ob_source_provider' => 'bds_crawler',
            'ob_source_airlines' => 'PATRIA',
            'ib_source_provider' => 'bds_crawler',
            'ib_source_airlines' => 'PATRIA',
        ])
            ->assertRedirect();

        $order = Order::with('flights')->firstOrFail();
        $outbound = $order->flights->firstWhere('direction', 'outbound');
        $inbound = $order->flights->firstWhere('direction', 'inbound');

        $this->assertSame('GOL', $outbound->cia);
        $this->assertSame('GOL', $outbound->operator);
        $this->assertSame('AZUL', $inbound->cia);
        $this->assertSame('AZUL', $inbound->operator);
        $this->assertSame('bds_crawler', $outbound->source_provider);
        $this->assertSame('PATRIA', $inbound->source_airlines);
    }

    private function providerQuery(array $overrides = []): array
    {
        return array_merge($this->searchQuery(), [
            'slot' => encrypt('vdp|GOL'),
        ], $overrides);
    }

    private function searchQuery(array $overrides = []): array
    {
        return array_merge([
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
            'trip_type' => 'oneway',
        ], $overrides);
    }

    private function flightPayload(array $overrides = []): array
    {
        return array_merge([
            'operator' => 'AZUL',
            'flight_number' => 'AD1234',
            'departure_time' => '10:00',
            'arrival_time' => '11:00',
            'departure_location' => 'GRU',
            'arrival_location' => 'SDU',
            'departure_label' => 'Guarulhos (GRU)',
            'arrival_label' => 'Santos Dumont (SDU)',
            'boarding_tax' => '30,00',
            'class_service' => 'Economy',
            'price_money' => '100,00',
            'price_miles' => '0',
            'price_miles_vip' => null,
            'total_flight_duration' => '01:00',
            'unique_id' => 'flight-coverage',
            'connection' => null,
        ], $overrides);
    }
}
