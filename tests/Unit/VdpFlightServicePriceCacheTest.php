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
