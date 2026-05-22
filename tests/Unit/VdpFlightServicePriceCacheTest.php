<?php

namespace Tests\Unit;

use App\Services\VdpFlightService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VdpFlightServicePriceCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
        ]);
    }

    public function test_patria_gol_conventional_flight_uses_gol_percentage_pricing(): void
    {
        Cache::forever('app_settings', [
            'pricing_miles_enabled' => true,
            'pricing_pct_enabled' => true,
            'pricing_pct_gol' => '15',
        ]);

        $service = app(VdpFlightService::class);
        $flight = [
            'operator' => 'PATRIA',
            'flight_number' => 'G3-1991',
            'price_money' => '1.000,00',
            'price_miles' => '0',
            'boarding_tax' => '50,00',
        ];

        $this->assertSame(1200.0, round($service->calculateFlightPrice($flight), 2));
        $this->assertSame('1150.00', $service->calculateBasePrice($flight));
    }

    public function test_interline_ad_flight_uses_azul_percentage_pricing(): void
    {
        Cache::forever('app_settings', [
            'pricing_miles_enabled' => true,
            'pricing_pct_enabled' => true,
            'pricing_pct_azul' => '15',
        ]);

        $service = app(VdpFlightService::class);
        $flight = [
            'operator' => 'AZUL',
            '_source_airlines' => 'INTERLINE',
            'flight_number' => 'AD4110',
            'price_money' => '1.000,00',
            'price_miles' => '203.000',
            'boarding_tax' => '50,00',
        ];

        $this->assertSame(1200.0, round($service->calculateFlightPrice($flight), 2));
        $this->assertSame('1150.00', $service->calculateBasePrice($flight));
    }

    public function test_miles_price_has_priority_over_percentage_pricing(): void
    {
        Cache::forever('app_settings', [
            'pricing_miles_enabled' => false,
            'pricing_pct_enabled' => true,
            'pricing_miles_gol' => '20',
            'pricing_pct_gol' => '100',
        ]);

        $service = app(VdpFlightService::class);
        $flight = [
            'operator' => 'GOL',
            'flight_number' => 'G3-1886',
            'price_money' => '1.000,00',
            'price_miles' => '10.000',
            'boarding_tax' => '50,00',
        ];

        $this->assertSame(250.0, round($service->calculateFlightPrice($flight), 2));
        $this->assertSame('200.00', $service->calculateBasePrice($flight));
    }

    public function test_percentage_pricing_is_used_when_miles_price_is_zero(): void
    {
        Cache::forever('app_settings', [
            'pricing_miles_enabled' => true,
            'pricing_pct_enabled' => true,
            'pricing_pct_gol' => '100',
        ]);

        $service = app(VdpFlightService::class);
        $flight = [
            'operator' => 'GOL',
            'flight_number' => 'G3-1886',
            'price_money' => '1.000,00',
            'price_miles' => '0',
            'boarding_tax' => '50,00',
        ];

        $this->assertSame(2050.0, round($service->calculateFlightPrice($flight), 2));
        $this->assertSame('2000.00', $service->calculateBasePrice($flight));
    }

    public function test_calculate_flight_price_uses_tax_alias_when_boarding_tax_is_missing(): void
    {
        Cache::forever('app_settings', [
            'pricing_pct_enabled' => false,
        ]);

        $service = app(VdpFlightService::class);
        $flight = [
            'operator' => 'GOL',
            'price_money' => '100.00',
            'price_miles' => '0',
            'tax' => '34.11',
        ];

        $this->assertSame(134.11, round($service->calculateFlightPrice($flight), 2));
        $this->assertSame('34.11', $service->resolveBoardingTax($flight));
    }

    public function test_nested_taxes_amount_is_normalized_as_boarding_tax(): void
    {
        $service = app(VdpFlightService::class);

        $flight = $service->normalizeFlightFields([
            'operator' => 'LATAM',
            'price_money' => '250,00',
            'price_miles' => '0',
            'taxes' => ['amount' => 62.62],
        ]);

        $this->assertSame('62.62', $flight['boarding_tax']);
        $this->assertSame(312.62, round($service->calculateFlightPrice($flight), 2));
    }

    public function test_money_parser_accepts_brazilian_and_decimal_dot_values(): void
    {
        $service = app(VdpFlightService::class);

        $this->assertSame('1234.56', $service->parseMoneyValue('R$ 1.234,56'));
        $this->assertSame('34.11', $service->parseMoneyValue('34.11'));
        $this->assertSame('1234', $service->parseMoneyValue('1.234'));
    }

    public function test_missing_boarding_tax_uses_configured_percentage_of_base_price(): void
    {
        Cache::forever('app_settings', [
            'pricing_miles_enabled' => true,
            'pricing_pct_enabled' => false,
            'pricing_miles_latam' => '30.00',
            'boarding_tax_fallback_pct' => '10',
        ]);

        $service = app(VdpFlightService::class);
        $flight = $service->normalizeFlightFields([
            'operator' => 'LATAM',
            'price_money' => '0,00',
            'price_miles' => '21691',
            'boarding_tax' => '0,00',
        ]);

        $this->assertSame('65.07', $flight['boarding_tax']);
        $this->assertSame(715.80, round($service->calculateFlightPrice($flight), 2));
    }

    public function test_patria_merge_keeps_cheapest_duplicate_and_unique_conventional_flights(): void
    {
        Cache::forever('app_settings', [
            'pricing_miles_enabled' => true,
            'pricing_pct_enabled' => true,
            'pricing_miles_gol' => '20',
            'pricing_pct_gol' => '0',
        ]);

        $service = app(VdpFlightService::class);
        $method = new \ReflectionMethod($service, 'mergeWithPatria');
        $method->setAccessible(true);

        $merged = $method->invoke($service, [[
            'operator' => 'GOL',
            'flight_number' => 'G3-1886',
            'departure_time' => '10:00',
            'price_money' => '1.000,00',
            'price_miles' => '20.000',
            'boarding_tax' => '50,00',
        ]], [[
            'operator' => 'PATRIA',
            'flight_number' => 'G3-1886',
            'departure_time' => '10:00',
            'price_money' => '100,00',
            'price_miles' => '0',
            'boarding_tax' => '50,00',
        ], [
            'operator' => 'PATRIA',
            'flight_number' => 'G3-1990',
            'departure_time' => '12:00',
            'price_money' => '200,00',
            'price_miles' => '0',
            'boarding_tax' => '50,00',
        ]]);

        $this->assertCount(2, $merged);
        $this->assertSame('PATRIA', $merged[0]['operator']);
        $this->assertSame('0', $merged[0]['price_miles']);
        $this->assertSame('G3-1990', $merged[1]['flight_number']);
    }

    public function test_active_provider_slots_include_patria_when_bds_provider_is_active(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
            'provider_gol' => 'bds_crawler',
            'provider_azul' => 'disabled',
            'provider_latam' => 'disabled',
            'bds_patria_enabled' => false,
        ]);

        $slots = app(VdpFlightService::class)->getActiveProviderSlots();

        $this->assertContains([
            'provider' => 'bds_crawler',
            'airlines' => 'GOL',
            'patria' => false,
        ], $slots);

        $this->assertContains([
            'provider' => 'bds_crawler',
            'airlines' => 'PATRIA',
            'patria' => true,
        ], $slots);

        $this->assertContains([
            'provider' => 'bds_crawler',
            'airlines' => 'INTERLINE',
            'patria' => false,
        ], $slots);

        $this->assertContains([
            'provider' => 'bds_crawler',
            'airlines' => 'TAP',
            'patria' => false,
        ], $slots);
    }

    public function test_forget_search_caches_clears_search_and_provider_search_caches(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
            'provider_gol' => 'bds_crawler',
            'provider_azul' => 'vdp',
            'provider_latam' => 'latam_crawler',
            'bds_patria_enabled' => true,
        ]);

        $service = app(VdpFlightService::class);
        $params = [
            'departure' => 'RIO',
            'arrival' => 'FOR',
            'outbound_date' => '2026-05-24',
            'inbound_date' => '2026-05-30',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
        ];
        $providerParams = ['cia' => 'all', ...$params];
        $fullSearchParams = $providerParams;
        unset($fullSearchParams['cia']);

        $providerKey = 'vdp_prov:test:bds_crawler:gol:'.md5(json_encode($providerParams));
        $patriaKey = 'vdp_prov:test:bds_crawler:patria:'.md5(json_encode($providerParams));
        $searchKey = 'vdp_search:test:'.md5(json_encode($providerParams));
        $legacySearchKey = 'vdp_search:test:'.md5(json_encode($fullSearchParams));
        foreach ([$providerKey, $patriaKey, $searchKey, $legacySearchKey] as $key) {
            Cache::put($key, ['cached' => true], now()->addMinutes(30));
        }

        $service->forgetSearchCaches($params);

        foreach ([$providerKey, $patriaKey, $searchKey, $legacySearchKey] as $key) {
            $this->assertFalse(Cache::has($key));
        }
    }

    public function test_search_flights_with_cache_info_caches_vdp_results(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
            'provider_gol' => 'vdp',
            'provider_azul' => 'disabled',
            'provider_latam' => 'disabled',
            'pricing_pct_enabled' => false,
        ]);
        config()->set('services.vdp.url', 'https://vdp.test');

        Http::fake([
            'https://vdp.test/api/search/flights' => Http::response([
                'outbound' => [
                    $this->flight('GOL', 'G31234', '10:00', '100,00', '30,00'),
                    $this->flight('GOL', 'G34321', '11:00', '90,00', '25,00'),
                ],
                'inbound' => [
                    $this->flight('GOL', 'G35678', '18:00', '110,00', '40,00'),
                ],
            ]),
        ]);

        $service = app(VdpFlightService::class);
        $params = $this->searchParams([
            'inbound_date' => '2026-06-15',
        ]);

        $first = $service->searchFlightsWithCacheInfo($params);
        $second = $service->searchFlightsWithCacheInfo($params);

        $this->assertFalse($first['from_cache']);
        $this->assertTrue($second['from_cache']);
        $this->assertCount(2, $first['data']['outbound']);

        Http::assertSentCount(1);
    }

    public function test_search_single_provider_filters_vdp_results_and_uses_cache(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
            'pricing_pct_enabled' => false,
        ]);
        config()->set('services.vdp.url', 'https://vdp.test');

        Http::fake([
            'https://vdp.test/api/search/flights' => Http::response([
                'outbound' => [
                    $this->flight('GOL', 'G31234', '10:00', '100,00', '30,00'),
                    $this->flight('LATAM', 'LA1234', '12:00', '200,00', '60,00'),
                ],
                'inbound' => [
                    $this->flight('AZUL', 'AD1234', '15:00', '100,00', '30,00'),
                    $this->flight('GOL', 'G35678', '18:00', '110,00', '40,00'),
                ],
            ]),
        ]);

        $service = app(VdpFlightService::class);
        $params = $this->searchParams(['inbound_date' => '2026-06-15']);

        $first = $service->searchSingleProvider($params, 'vdp', 'GOL');
        $second = $service->searchSingleProvider($params, 'vdp', 'GOL');

        $this->assertSame('G31234', $first['outbound'][0]['flight_number']);
        $this->assertSame('G35678', $first['inbound'][0]['flight_number']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_search_single_provider_calls_bds_with_headers_and_handles_failures(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
            'bds_crawler_timeout' => 12,
        ]);
        config()->set('services.bds_crawler.url', 'https://bds.test');
        config()->set('services.bds_crawler.api_key', 'secret-key');

        Http::fake([
            'https://bds.test/api/search*' => Http::sequence()
                ->push([
                    'outbound' => [$this->flight('AZUL', 'AD1234', '10:00', '100,00', '30,00')],
                    'inbound' => [],
                ])
                ->push(['message' => 'erro'], 500),
        ]);

        $service = app(VdpFlightService::class);

        $success = $service->searchSingleProvider($this->searchParams(), 'bds_crawler', 'AZUL,LATAM');
        $failed = $service->searchSingleProvider($this->searchParams([
            'outbound_date' => '2026-06-11',
        ]), 'bds_crawler', 'AZUL');

        $this->assertSame('AD1234', $success['outbound'][0]['flight_number']);
        $this->assertSame(['outbound' => [], 'inbound' => []], $failed);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://bds.test/api/search?origin=GRU&destination=SDU&outbound_date=2026-06-10&adults=1&children=0&infants=0&cabin=EC&airlines=AZUL%2CLATAM'
                && $request->hasHeader('X-API-KEY', 'secret-key');
        });
    }

    public function test_revalidate_flight_pair_matches_by_flight_number_when_unique_id_changes(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
            'pricing_pct_enabled' => false,
        ]);
        config()->set('services.vdp.url', 'https://vdp.test');

        Http::fake([
            'https://vdp.test/api/search/flights' => Http::response([
                'outbound' => [
                    $this->flight('GOL', 'G3 1234', '10:00', '140,00', '35,00', 'new-ob'),
                ],
                'inbound' => [
                    $this->flight('GOL', 'G3-5678', '18:00', '150,00', '45,00', 'new-ib'),
                ],
            ]),
        ]);

        $service = app(VdpFlightService::class);
        $result = $service->revalidateFlightPair(
            $this->searchParams(['inbound_date' => '2026-06-15']),
            [
                ...$this->flight('GOL', 'G3-1234', '10:00', '100,00', '30,00', 'old-ob'),
                '_source_provider' => 'vdp',
                '_source_airlines' => 'GOL',
            ],
            [
                ...$this->flight('GOL', 'G35678', '18:00', '110,00', '40,00', 'old-ib'),
                '_source_provider' => 'vdp',
                '_source_airlines' => 'GOL',
            ],
        );

        $this->assertSame('new-ob', $result['outbound']['unique_id']);
        $this->assertSame('new-ib', $result['inbound']['unique_id']);
    }

    public function test_revalidate_flight_pair_keeps_original_when_provider_returns_empty(): void
    {
        Cache::forever('app_settings', [
            'pricing_version' => 'test',
        ]);
        config()->set('services.vdp.url', 'https://vdp.test');

        Http::fake([
            'https://vdp.test/api/search/flights' => Http::response([
                'outbound' => [],
                'inbound' => [],
            ]),
        ]);

        $service = app(VdpFlightService::class);
        $original = [
            ...$this->flight('GOL', 'G31234', '10:00', '100,00', '30,00', 'old-ob'),
            '_source_provider' => 'vdp',
            '_source_airlines' => 'GOL',
        ];

        $result = $service->revalidateFlightPair($this->searchParams(), $original);

        $this->assertSame($original, $result['outbound']);
        $this->assertNull($result['inbound']);
    }

    private function searchParams(array $overrides = []): array
    {
        return array_merge([
            'cia' => 'all',
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
            'inbound_date' => null,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
        ], $overrides);
    }

    private function flight(
        string $operator,
        string $flightNumber,
        string $departureTime,
        string $priceMoney,
        string $boardingTax,
        ?string $uniqueId = null,
    ): array {
        return [
            'operator' => $operator,
            'flight_number' => $flightNumber,
            'departure_time' => $departureTime,
            'arrival_time' => '11:00',
            'departure_location' => 'GRU',
            'arrival_location' => 'SDU',
            'boarding_tax' => $boardingTax,
            'class_service' => 'Economy',
            'price_money' => $priceMoney,
            'price_miles' => '0',
            'total_flight_duration' => '01:00',
            'unique_id' => $uniqueId ?? strtolower(str_replace([' ', '-'], '', $flightNumber)),
            'connection' => null,
        ];
    }
}
