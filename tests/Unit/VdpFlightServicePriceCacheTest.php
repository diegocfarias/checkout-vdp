<?php

namespace Tests\Unit;

use App\Services\VdpFlightService;
use Illuminate\Support\Facades\Cache;
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

    public function test_roundtrip_direction_cache_returns_outbound_plus_inbound(): void
    {
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

        Cache::put($this->directionKey($service, 'RIO', 'FOR', '2026-05-24', $params), 123.45);
        Cache::put($this->directionKey($service, 'FOR', 'RIO', '2026-05-30', $params), 234.55);

        $this->assertSame(358.0, $service->getMinPriceFromCache($params));
    }

    public function test_roundtrip_direction_cache_does_not_return_outbound_only(): void
    {
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

        Cache::put($this->directionKey($service, 'RIO', 'FOR', '2026-05-24', $params), 123.45);

        $this->assertNull($service->getMinPriceFromCache($params));
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

    public function test_patria_merge_keeps_miles_fare_over_cheaper_conventional_duplicate(): void
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
        ]]);

        $this->assertCount(1, $merged);
        $this->assertSame('GOL', $merged[0]['operator']);
        $this->assertSame('20.000', $merged[0]['price_miles']);
    }

    public function test_forget_search_caches_clears_provider_search_and_direction_prices(): void
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

        $providerKey = 'vdp_prov:test:bds_crawler:gol:' . md5(json_encode($providerParams));
        $patriaKey = 'vdp_prov:test:bds_crawler:patria:' . md5(json_encode($providerParams));
        $searchKey = 'vdp_search:test:' . md5(json_encode($providerParams));
        $legacySearchKey = 'vdp_search:test:' . md5(json_encode($fullSearchParams));
        $outboundKey = $this->directionKey($service, 'RIO', 'FOR', '2026-05-24', $params);
        $inboundKey = $this->directionKey($service, 'FOR', 'RIO', '2026-05-30', $params);

        foreach ([$providerKey, $patriaKey, $searchKey, $legacySearchKey, $outboundKey, $inboundKey] as $key) {
            Cache::put($key, ['cached' => true], now()->addMinutes(30));
        }

        $service->forgetSearchCaches($params);

        foreach ([$providerKey, $patriaKey, $searchKey, $legacySearchKey, $outboundKey, $inboundKey] as $key) {
            $this->assertFalse(Cache::has($key));
        }
    }

    private function directionKey(VdpFlightService $service, string $departure, string $arrival, string $date, array $params): string
    {
        $method = new \ReflectionMethod($service, 'directionPriceKey');
        $method->setAccessible(true);

        return $method->invoke($service, 'test', $departure, $arrival, $date, [
            'adults' => $params['adults'],
            'children' => $params['children'],
            'infants' => $params['infants'],
            'cabin' => $params['cabin'],
        ]);
    }
}
