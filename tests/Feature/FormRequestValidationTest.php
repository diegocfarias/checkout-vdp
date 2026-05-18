<?php

namespace Tests\Feature;

use App\Http\Requests\FlightSearchRequest;
use App\Models\Setting;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        config()->set('services.api.key', 'api-secret');
    }

    protected function tearDown(): void
    {
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_register_request_rejects_invalid_cpf(): void
    {
        $this->from(route('customer.register'))
            ->post(route('customer.register.submit'), [
                'name' => 'Maria Silva',
                'email' => 'maria@example.com',
                'document' => '111.111.111-11',
                'phone' => '11999999999',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('customer.register'))
            ->assertSessionHasErrors('document');
    }

    public function test_store_order_request_normalizes_empty_return_flight(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('searchAndFilter')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['volta'] === null))
            ->andReturn([
                [
                    'direction' => 'outbound',
                    'cia' => 'AZUL',
                    'operator' => 'AZUL',
                    'unique_id' => 'outbound-id',
                    'money_price' => '100.00',
                    'tax' => '30.00',
                ],
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->withHeader('X-API-KEY', 'api-secret')
            ->postJson('/api/orders', [
                'ida' => [
                    'miles_price' => '10000',
                    'money_price' => '100.00',
                    'tax' => '30.00',
                    'unique_id' => 'outbound-id',
                    'outbound_date' => '2026-06-11',
                    'cia' => 'AZUL',
                ],
                'volta' => [],
                'departure_iata' => 'CNF',
                'arrival_iata' => 'VIX',
                'total_adults' => 1,
                'total_children' => 0,
                'total_babies' => 0,
                'userId' => 'user-123',
                'conversationId' => 'conversation-123',
                'cabin' => 'EC',
            ])
            ->assertCreated();
    }

    public function test_store_order_passengers_request_rejects_wrong_count_and_duplicate_cpf(): void
    {
        $order = $this->createOrder([
            'total_adults' => 2,
            'total_children' => 0,
        ]);
        $this->addFlight($order);

        $this->from(route('checkout.passengers', $order->token))
            ->post(route('checkout.store', $order), [
                'passengers' => [
                    $this->passengerPayload('Maria Silva'),
                    $this->passengerPayload('João Silva'),
                ],
                'payment_method' => 'pix',
                'payer_name' => 'Maria Silva',
                'payer_email' => 'maria@example.com',
                'payer_document' => '529.982.247-25',
            ])
            ->assertRedirect(route('checkout.passengers', $order->token))
            ->assertSessionHasErrors('passengers.1.document');

        $singlePassengerOrder = $this->createOrder([
            'total_adults' => 2,
            'total_children' => 0,
        ]);
        $this->addFlight($singlePassengerOrder);

        $this->from(route('checkout.passengers', $singlePassengerOrder->token))
            ->post(route('checkout.store', $singlePassengerOrder), [
                'passengers' => [
                    $this->passengerPayload('Maria Silva'),
                ],
                'payment_method' => 'pix',
                'payer_name' => 'Maria Silva',
                'payer_email' => 'maria@example.com',
                'payer_document' => '529.982.247-25',
            ])
            ->assertRedirect(route('checkout.passengers', $singlePassengerOrder->token))
            ->assertSessionHasErrors('passengers');
    }

    public function test_flight_search_request_rejects_invalid_routes_dates_and_infants(): void
    {
        $request = new FlightSearchRequest;

        $validator = Validator::make([
            'departure' => 'GRU',
            'arrival' => 'GRU',
            'outbound_date' => now()->subDay()->format('Y-m-d'),
            'inbound_date' => now()->subDays(2)->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'infants' => 2,
            'cabin' => 'XX',
            'trip_type' => 'roundtrip',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('arrival', $validator->errors()->messages());
        $this->assertArrayHasKey('outbound_date', $validator->errors()->messages());
        $this->assertArrayHasKey('inbound_date', $validator->errors()->messages());
        $this->assertArrayHasKey('infants', $validator->errors()->messages());
        $this->assertArrayHasKey('cabin', $validator->errors()->messages());
    }

    private function passengerPayload(string $name): array
    {
        return [
            'nationality' => 'BR',
            'full_name' => $name,
            'document' => '529.982.247-25',
            'birth_date' => '1990-01-01',
            'email' => 'passageiro@example.com',
            'phone' => '11999999999',
        ];
    }
}
