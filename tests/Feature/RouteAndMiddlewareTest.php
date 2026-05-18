<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SavedPassenger;
use App\Models\Setting;
use App\Models\ShowcaseRoute;
use App\Models\User;
use App\Services\UnsplashService;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class RouteAndMiddlewareTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_public_auth_and_tracking_pages_render(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertViewIs('auth.login');

        $this->get(route('customer.register'))
            ->assertOk()
            ->assertViewIs('auth.register');

        $this->get(route('customer.password.request'))
            ->assertOk()
            ->assertViewIs('auth.forgot-password');

        $this->get(route('customer.password.reset', ['token' => 'reset-token', 'email' => 'cliente@example.com']))
            ->assertOk()
            ->assertViewIs('auth.reset-password')
            ->assertViewHas('token', 'reset-token')
            ->assertViewHas('email', 'cliente@example.com');

        $this->get(route('tracking.form'))
            ->assertOk()
            ->assertViewIs('tracking.search');
    }

    public function test_customer_guard_and_active_middleware_redirect_guests_and_pending_customers(): void
    {
        $this->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.login'));

        $pending = $this->createCustomer([
            'email' => 'pendente@example.com',
            'password' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($pending, 'customer')
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.password.request'))
            ->assertSessionHas('status', 'Complete seu cadastro definindo uma senha para acessar sua conta.');

        $active = $this->createCustomer([
            'email' => 'ativo@example.com',
            'password' => 'password',
            'status' => 'active',
        ]);
        SavedPassenger::create([
            'customer_id' => $active->id,
            'full_name' => 'Maria Silva',
            'nationality' => 'BR',
            'document' => '52998224725',
            'birth_date' => '1990-01-01',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ]);

        $this->actingAs($active, 'customer')
            ->get(route('customer.profile'))
            ->assertOk()
            ->assertViewIs('customer.profile');

        $this->actingAs($active, 'customer')
            ->get(route('customer.passengers'))
            ->assertOk()
            ->assertViewIs('customer.passengers')
            ->assertViewHas('savedPassengers', fn ($passengers): bool => $passengers->count() === 1);

        $this->actingAs($active, 'customer')
            ->get(route('customer.support.index'))
            ->assertOk()
            ->assertViewIs('customer.support-index');
    }

    public function test_complete_registration_requires_google_session_and_renders_with_session_data(): void
    {
        $this->get(route('customer.complete-registration'))
            ->assertRedirect(route('customer.login'));

        $googleUser = [
            'id' => 'google-123',
            'name' => 'Cliente Google',
            'email' => 'google@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ];

        $this->withSession(['google_user' => $googleUser])
            ->get(route('customer.complete-registration'))
            ->assertOk()
            ->assertViewIs('auth.complete-registration')
            ->assertViewHas('googleUser', $googleUser);
    }

    public function test_home_captures_referral_cookie_and_showcase_price_includes_pix_discount(): void
    {
        Setting::set('referral_enabled', true, 'boolean');
        Setting::set('referral_cookie_days', 15, 'integer');
        Setting::set('showcase_sort_mode', 'cheapest', 'string');
        Setting::set('showcase_max_cards', 2, 'integer');
        Setting::set('gateway_pix', 'c6bank', 'string');
        Setting::set('pix_discount', '10', 'string');

        $this->createCustomer([
            'email' => 'afiliado@example.com',
            'is_affiliate' => true,
            'referral_code' => 'IND-ABC123',
        ]);
        $expensive = $this->createShowcaseRoute([
            'arrival_iata' => 'VIX',
            'arrival_city' => 'Vitória',
            'cached_price' => 900,
            'sort_order' => 1,
        ]);
        $cheap = $this->createShowcaseRoute([
            'arrival_iata' => 'SDU',
            'arrival_city' => 'Rio de Janeiro',
            'cached_price' => 500,
            'cached_flight_data' => [
                'base_price' => 400,
                'tax' => 100,
            ],
            'sort_order' => 2,
        ]);
        $this->createShowcaseRoute([
            'arrival_iata' => 'REC',
            'arrival_city' => 'Recife',
            'cached_price' => null,
        ]);

        $this->get('/?ref=ind-abc123')
            ->assertOk()
            ->assertViewIs('search.home')
            ->assertCookie('ref_code', 'IND-ABC123')
            ->assertViewHas('showcaseRoutes', fn ($routes): bool => $routes->pluck('id')->all() === [$cheap->id, $expensive->id]);

        $this->getJson(route('showcase.price', $cheap))
            ->assertOk()
            ->assertJson([
                'price' => 500,
                'formatted_price' => 'R$ 500,00',
                'pix_price' => 460,
                'formatted_pix_price' => 'R$ 460,00',
                'airline' => 'AZUL',
            ]);
    }

    public function test_date_prices_reads_cached_prices_for_requested_range(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class);
        $vdp->shouldReceive('getMinPriceFromCache')
            ->times(3)
            ->andReturnUsing(fn (array $params): ?float => match ($params['outbound_date']) {
                '2026-06-01' => 350.25,
                '2026-06-02' => null,
                '2026-06-03' => 299.99,
            });
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->getJson(route('api.date-prices').'?'.http_build_query([
            'departure' => 'gru',
            'arrival' => 'sdu',
            'cabin' => 'EC',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-03',
            'trip_type' => 'roundtrip',
            'inbound_offset' => 4,
        ]))
            ->assertOk()
            ->assertExactJson([
                'prices' => [
                    '2026-06-01' => 350.25,
                    '2026-06-02' => null,
                    '2026-06-03' => 299.99,
                ],
            ]);
    }

    public function test_search_results_render_and_provider_endpoint_rejects_invalid_slots(): void
    {
        $vdp = Mockery::mock(VdpFlightService::class)->makePartial();
        $vdp->shouldReceive('getActiveProviderSlots')
            ->once()
            ->andReturn([
                ['provider' => 'vdp', 'airlines' => 'GOL,AZUL', 'patria' => false],
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->get(route('search.results', [
            'departure' => 'gru',
            'arrival' => 'sdu',
            'outbound_date' => '2026-06-10',
            'inbound_date' => null,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
            'trip_type' => 'oneway',
        ]))
            ->assertOk()
            ->assertViewIs('search.results')
            ->assertViewHas('providerSlots', fn (array $slots): bool => count($slots) === 1);

        $this->assertDatabaseHas('flight_searches', [
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'trip_type' => 'oneway',
        ]);

        $this->getJson(route('api.search.provider', [
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
            'slot' => 'invalid-token',
        ]))
            ->assertStatus(400)
            ->assertExactJson(['outbound' => [], 'inbound' => []]);
    }

    public function test_admin_showcase_image_search_requires_auth_and_returns_photos(): void
    {
        $this->postJson(route('admin.showcase.search-images'), ['query' => 'Rio de Janeiro'])
            ->assertUnauthorized();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $unsplash = Mockery::mock(UnsplashService::class);
        $unsplash->shouldReceive('searchPhotos')
            ->once()
            ->with('Rio de Janeiro', 6)
            ->andReturn([
                ['url' => 'https://images.test/rio.jpg', 'thumb' => 'https://images.test/thumb.jpg', 'credit' => 'Teste / Unsplash'],
            ]);
        $this->app->instance(UnsplashService::class, $unsplash);

        $this->actingAs($admin)
            ->postJson(route('admin.showcase.search-images'), ['query' => 'Rio de Janeiro'])
            ->assertOk()
            ->assertExactJson([
                'photos' => [
                    ['url' => 'https://images.test/rio.jpg', 'thumb' => 'https://images.test/thumb.jpg', 'credit' => 'Teste / Unsplash'],
                ],
            ]);
    }

    public function test_dev_fake_checkout_and_appmax_validation_routes(): void
    {
        config()->set('app.debug', false);

        $this->get(route('dev.fake-checkout'))
            ->assertNotFound();

        config()->set('app.debug', true);

        $this->get(route('dev.fake-checkout'))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'departure_iata' => 'GRU',
            'arrival_iata' => 'GIG',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_flights', [
            'direction' => 'outbound',
            'flight_number' => '1234',
            'money_price' => '3.00',
            'tax' => '2.00',
        ]);

        $this->getJson(route('appmax.validate'))
            ->assertOk()
            ->assertJson(['status' => 'ok', 'app' => 'checkout-vdp']);

        $this->postJson(route('appmax.validate'), [
            'app_id' => 'app',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'external_key' => 'external',
        ])
            ->assertOk()
            ->assertJsonStructure(['external_id']);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '52998224725',
            'phone' => '11999999999',
            'status' => 'active',
            'is_affiliate' => false,
        ], $attributes));
    }

    private function createShowcaseRoute(array $attributes = []): ShowcaseRoute
    {
        return ShowcaseRoute::create(array_merge([
            'departure_iata' => 'GRU',
            'departure_city' => 'São Paulo',
            'arrival_iata' => 'CNF',
            'arrival_city' => 'Belo Horizonte',
            'trip_type' => 'roundtrip',
            'cabin' => 'EC',
            'search_window_days' => 30,
            'return_stay_days' => 5,
            'sample_dates_count' => 3,
            'is_active' => true,
            'sort_order' => 1,
            'cached_price' => 700,
            'cached_date' => '2026-06-10',
            'cached_return_date' => '2026-06-15',
            'cached_airline' => 'AZUL',
        ], $attributes));
    }
}
