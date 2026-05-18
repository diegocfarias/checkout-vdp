<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FlightSearchEdgeTest extends TestCase
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

    public function test_roundtrip_selection_requires_inbound_flight(): void
    {
        $search = $this->createFlightSearch([
            'trip_type' => 'roundtrip',
            'inbound_date' => '2026-06-15',
        ]);

        $this->from(route('search.results', [
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
            'inbound_date' => '2026-06-15',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
            'trip_type' => 'roundtrip',
        ]))
            ->post(route('search.select'), [
                'search_id' => $search->id,
                'outbound' => json_encode($this->flightPayload()),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Selecione também um voo de volta para continuar com a compra de ida e volta.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirmed_selection_creates_mobile_order_with_provider_metadata(): void
    {
        Setting::set('order_expiration_minutes', 45, 'integer');
        $search = $this->createFlightSearch([
            'trip_type' => 'oneway',
            'adults' => 2,
            'children' => 1,
            'infants' => 1,
        ]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->post(route('search.select'), [
                'search_id' => $search->id,
                'outbound' => json_encode($this->flightPayload([
                    'unique_id' => 'selected-outbound',
                    '_source_provider' => 'bds_crawler',
                    '_source_airlines' => 'AZUL',
                ])),
                'confirmed' => '1',
                'ob_provider' => 'BDS Crawler',
                'ob_pricing_type' => 'milhas',
                'ob_source_provider' => 'bds_crawler',
                'ob_source_airlines' => 'AZUL',
            ])
            ->assertRedirect();

        $order = Order::with('flights')->firstOrFail();

        $this->assertSame(2, $order->total_adults);
        $this->assertSame(1, $order->total_children);
        $this->assertSame(1, $order->total_babies);
        $this->assertSame('mobile', $order->device_type);
        $this->assertSame('pending', $order->status);
        $this->assertEqualsWithDelta(45, now()->diffInMinutes($order->expires_at), 1);

        $flight = $order->flights->first();
        $this->assertSame('outbound', $flight->direction);
        $this->assertSame('AZUL', $flight->cia);
        $this->assertSame('selected-outbound', $flight->unique_id);
        $this->assertSame('100.00', $flight->money_price);
        $this->assertSame('30.00', $flight->tax);
        $this->assertSame('BDS Crawler', $flight->provider);
        $this->assertSame('milhas', $flight->pricing_type);
        $this->assertSame('bds_crawler', $flight->source_provider);
        $this->assertSame('AZUL', $flight->source_airlines);
    }

    public function test_provider_endpoint_returns_sanitized_flights_with_metadata(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class)->makePartial();
        $vdp->shouldReceive('searchSingleProvider')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => $params['departure'] === 'GRU'
                && $params['arrival'] === 'SDU'
                && $params['adults'] === 1), 'vdp', 'GOL')
            ->andReturn([
                'outbound' => [
                    $this->flightPayload([
                        'operator' => 'GOL',
                        'flight_number' => 'G31234',
                        'unique_id' => 'provider-flight',
                        'secret' => 'should-not-leak',
                    ]),
                    $this->flightPayload([
                        'operator' => 'GOL',
                        'unique_id' => 'zero-price',
                        'price_money' => '0',
                        'boarding_tax' => '0',
                    ]),
                ],
                'inbound' => [],
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $response = $this->getJson(route('api.search.provider', [
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
            'slot' => encrypt('vdp|GOL'),
        ]))->assertOk();

        $outbound = $response->json('outbound');

        $this->assertCount(1, $outbound);
        $this->assertSame('provider-flight', $outbound[0]['unique_id']);
        $this->assertEquals(130.0, $outbound[0]['calculated_price']);
        $this->assertSame('VDP', $outbound[0]['_provider']);
        $this->assertSame('convencional', $outbound[0]['_pricing_type']);
        $this->assertSame('vdp', $outbound[0]['_source_provider']);
        $this->assertSame('GOL', $outbound[0]['_source_airlines']);
        $this->assertArrayNotHasKey('secret', $outbound[0]);
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
            'unique_id' => 'flight-edge',
            'connection' => null,
        ], $overrides);
    }
}
