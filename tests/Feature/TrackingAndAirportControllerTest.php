<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class TrackingAndAirportControllerTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_tracking_search_verifies_document_and_allows_order_view(): void
    {
        $order = $this->createOrder([
            'tracking_code' => 'VDP-ABC1',
        ]);
        $this->addFlight($order, [
            'baggage' => [
                'personal_item' => ['included' => true, 'quantity' => 1, 'weight' => '10kg'],
                'carry_on' => ['included' => true, 'quantity' => 1, 'weight' => '10kg'],
                'checked' => ['included' => false, 'quantity' => 0, 'weight' => null],
            ],
        ]);
        $this->addPassenger($order, [
            'document' => '529.982.247-25',
        ]);

        $this->post(route('tracking.search'), [
            'tracking_code' => ' vdp-abc1 ',
            'document' => '529.982.247-25',
        ])
            ->assertRedirect(route('tracking.show', 'VDP-ABC1'))
            ->assertSessionHas("tracking_verified_{$order->tracking_code}", true);

        $this->get(route('tracking.show', 'vdp-abc1'))
            ->assertOk()
            ->assertViewIs('tracking.show')
            ->assertViewHas('order', fn ($viewOrder): bool => $viewOrder->id === $order->id)
            ->assertSee('Mala de mao inclusa', false);
    }

    public function test_tracking_search_rejects_invalid_document_and_show_requires_verification(): void
    {
        $order = $this->createOrder([
            'tracking_code' => 'VDP-ABC2',
        ]);
        $this->addPassenger($order, [
            'document' => '529.982.247-25',
        ]);

        $this->from(route('tracking.form'))
            ->post(route('tracking.search'), [
                'tracking_code' => 'VDP-ABC2',
                'document' => '111.444.777-35',
            ])
            ->assertRedirect(route('tracking.form'))
            ->assertSessionHasErrors('tracking_code');

        $this->get(route('tracking.show', 'VDP-ABC2'))
            ->assertRedirect(route('tracking.form'))
            ->assertSessionHasErrors('tracking_code');
    }

    public function test_tracking_show_accepts_order_token_without_prior_session(): void
    {
        $order = $this->createOrder([
            'tracking_code' => 'VDP-TOK1',
        ]);

        $this->get(route('tracking.show', [
            'trackingCode' => 'VDP-TOK1',
            'token' => $order->token,
        ]))
            ->assertOk()
            ->assertViewIs('tracking.show')
            ->assertSessionHas("tracking_verified_{$order->tracking_code}", true);
    }

    public function test_tracking_show_rejects_invalid_token_without_prior_session(): void
    {
        $order = $this->createOrder([
            'tracking_code' => 'VDP-TOK2',
        ]);

        $this->get(route('tracking.show', [
            'trackingCode' => 'VDP-TOK2',
            'token' => $order->token.'-invalid',
        ]))
            ->assertRedirect(route('tracking.form'))
            ->assertSessionHasErrors('tracking_code')
            ->assertSessionMissing("tracking_verified_{$order->tracking_code}");
    }

    public function test_tracking_show_displays_completed_loc_and_status_history(): void
    {
        $order = $this->createOrder([
            'tracking_code' => 'VDP-DONE',
            'status' => 'completed',
        ]);
        $this->addFlight($order, [
            'direction' => 'outbound',
            'cia' => 'LATAM',
            'flight_number' => 'LA1234',
            'departure_location' => 'GRU',
            'arrival_location' => 'MIA',
            'loc' => 'XYZ789',
            'price_miles' => '987654',
            'paid_boarding_tax' => 456.78,
        ]);
        $order->statusHistories()->create([
            'status' => 'awaiting_emission',
            'description' => 'Pagamento confirmado',
        ]);
        $order->statusHistories()->create([
            'status' => 'completed',
            'description' => 'Passagens emitidas',
        ]);

        $this->get(route('tracking.show', [
            'trackingCode' => 'VDP-DONE',
            'token' => $order->token,
        ]))
            ->assertOk()
            ->assertSee('Passagens emitidas')
            ->assertSee('LOC Ida')
            ->assertSee('XYZ789')
            ->assertSee('Pagamento confirmado')
            ->assertDontSee('987654')
            ->assertDontSee('456,78');
    }

    public function test_airport_search_posts_to_vdp_api_and_caches_results(): void
    {
        config()->set('services.vdp.url', 'https://vdp.test/');

        Http::fake([
            'https://vdp.test/api/airports' => Http::response([
                ['iata' => 'GRU', 'name' => 'Guarulhos'],
            ]),
        ]);

        $payload = ['term' => '  Gru  '];

        $first = $this->postJson('/api/airports', $payload)
            ->assertOk()
            ->json();
        $second = $this->postJson('/api/airports', $payload)
            ->assertOk()
            ->json();

        $this->assertSame($first, $second);
        $this->assertSame('GRU', $first[0]['iata']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://vdp.test/api/airports'
            && $request['term'] === 'gru');
    }

    public function test_airport_search_returns_empty_array_on_provider_failure(): void
    {
        config()->set('services.vdp.url', 'https://vdp.test');

        Http::fake([
            'https://vdp.test/api/airports' => Http::response(['error' => true], 500),
        ]);

        $this->postJson('/api/airports', ['term' => 'Rio'])
            ->assertOk()
            ->assertExactJson([]);
    }
}
