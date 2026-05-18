<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CreateOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        config()->set('app.url', 'https://checkout.test');
        config()->set('services.api.key', 'api-secret');
        config()->set('services.botpress.webhook_url', 'https://botpress.test/webhook');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_it_rejects_requests_without_api_key(): void
    {
        $this->postJson('/api/orders', $this->validPayload())
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_it_creates_order_with_flights_and_notifies_botpress(): void
    {
        Carbon::setTestNow('2026-05-14 09:00:00');
        Setting::set('order_expiration_minutes', 45, 'integer');
        Http::fake([
            'https://botpress.test/webhook' => Http::response(['ok' => true]),
        ]);

        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('searchAndFilter')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['userId'] === 'user-123'))
            ->andReturn([
                [
                    'direction' => 'outbound',
                    'cia' => 'AZUL',
                    'operator' => 'AZUL',
                    'flight_number' => 'AD1234',
                    'departure_time' => '10:00',
                    'arrival_time' => '11:00',
                    'departure_location' => 'CNF',
                    'arrival_location' => 'VIX',
                    'departure_label' => 'Confins (CNF)',
                    'arrival_label' => 'Vitória (VIX)',
                    'boarding_tax' => '99,73',
                    'class_service' => 'Economy',
                    'price_money' => '383,31',
                    'price_miles' => '10000',
                    'total_flight_duration' => '01:00',
                    'unique_id' => 'azul-outbound',
                    'money_price' => '383.31',
                    'tax' => '99.73',
                ],
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $response = $this->withHeader('X-API-KEY', 'api-secret')
            ->postJson('/api/orders', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('link', fn (string $link): bool => str_starts_with($link, 'https://checkout.test/r/'));

        $this->assertDatabaseHas('orders', [
            'total_adults' => 1,
            'total_children' => 0,
            'total_babies' => 0,
            'user_id' => 'user-123',
            'conversation_id' => 'conversation-123',
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_flights', [
            'direction' => 'outbound',
            'operator' => 'AZUL',
            'unique_id' => 'azul-outbound',
            'tax' => '99.73',
        ]);

        $order = \App\Models\Order::firstOrFail();
        $this->assertTrue($order->expires_at->equalTo(Carbon::parse('2026-05-14 09:45:00')));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://botpress.test/webhook'
                && $request['conversationId'] === 'conversation-123'
                && $request['userId'] === 'user-123'
                && str_starts_with($request['message'], 'https://checkout.test/r/');
        });
    }

    public function test_it_returns_validation_error_when_vdp_service_cannot_find_selected_flight(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('searchAndFilter')
            ->once()
            ->andThrow(new \RuntimeException('Voo selecionado não encontrado.'));
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->withHeader('X-API-KEY', 'api-secret')
            ->postJson('/api/orders', $this->validPayload())
            ->assertUnprocessable()
            ->assertJson(['message' => 'Voo selecionado não encontrado.']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'ida' => [
                'miles_price' => '10000',
                'money_price' => '383.31',
                'tax' => '99.73',
                'unique_id' => 'azul-outbound',
                'outbound_date' => '2026-06-11',
                'cia' => 'AZUL',
            ],
            'volta' => null,
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
            'total_adults' => 1,
            'total_children' => 0,
            'total_babies' => 0,
            'userId' => 'user-123',
            'conversationId' => 'conversation-123',
            'cabin' => 'EC',
        ], $overrides);
    }
}
