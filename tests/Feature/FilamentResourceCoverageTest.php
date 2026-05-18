<?php

namespace Tests\Feature;

use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Filament\Resources\CouponResource\Pages\ViewCoupon;
use App\Filament\Resources\IssuerResource;
use App\Filament\Resources\IssuerResource\Pages\CreateIssuer;
use App\Filament\Resources\IssuerResource\Pages\EditIssuer;
use App\Filament\Resources\IssuerResource\Pages\ListIssuers;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\ReferralResource\Pages\ListReferrals;
use App\Filament\Resources\ReferralResource\Pages\ViewReferral;
use App\Filament\Resources\ShowcaseRefreshLogResource\Pages\ListShowcaseRefreshLogs;
use App\Filament\Resources\ShowcaseRouteResource\Pages\CreateShowcaseRoute;
use App\Filament\Resources\ShowcaseRouteResource\Pages\EditShowcaseRoute;
use App\Filament\Resources\ShowcaseRouteResource\Pages\ListShowcaseRoutes;
use App\Jobs\RefreshShowcaseRoute;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\ShowcaseRefreshLog;
use App\Models\ShowcaseRoute;
use App\Models\User;
use App\Services\UnsplashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class FilamentResourceCoverageTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    public function test_coupon_resource_pages_create_list_view_edit_and_protect_used_coupon(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = $this->createCustomer();

        Livewire::actingAs($admin)
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'save15',
                'type' => 'percent',
                'value' => '15',
                'max_discount' => '80',
                'usage_limit' => 3,
                'active' => true,
                'cumulative_with_pix' => false,
                'starts_at' => '2026-05-01 10:00:00',
                'expires_at' => '2026-06-01 10:00:00',
                'customers' => [$customer->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::where('code', 'SAVE15')->firstOrFail();

        $this->assertEqualsWithDelta(15, (float) $coupon->value, 0.01);
        $this->assertFalse($coupon->cumulative_with_pix);
        $this->assertTrue($coupon->customers()->whereKey($customer->id)->exists());

        Livewire::actingAs($admin)
            ->test(ListCoupons::class)
            ->assertSee('SAVE15')
            ->assertSee('Percentual');

        Livewire::actingAs($admin)
            ->test(ViewCoupon::class, ['record' => $coupon->getRouteKey()])
            ->assertSee('SAVE15')
            ->assertSee('Clientes vinculados');

        Livewire::actingAs($admin)
            ->test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->fillForm([
                'code' => 'save20',
                'type' => 'fixed',
                'value' => '20',
                'max_discount' => null,
                'usage_limit' => 4,
                'active' => true,
                'cumulative_with_pix' => true,
                'starts_at' => null,
                'expires_at' => null,
                'customers' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'SAVE20',
            'type' => 'fixed',
            'usage_limit' => 4,
        ]);

        $used = Coupon::create([
            'code' => 'USED10',
            'type' => 'fixed',
            'value' => 10,
            'usage_count' => 1,
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EditCoupon::class, ['record' => $used->getRouteKey()])
            ->assertRedirect(CouponResource::getUrl('view', ['record' => $used]));
    }

    public function test_showcase_resource_pages_create_edit_refresh_and_log_actions(): void
    {
        Bus::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $unsplash = Mockery::mock(UnsplashService::class);
        $unsplash->shouldReceive('searchCityPhoto')
            ->twice()
            ->andReturn(
                ['url' => 'https://img.test/recife.jpg', 'credit' => 'Foto Recife'],
                ['url' => 'https://img.test/salvador.jpg', 'credit' => 'Foto Salvador'],
            );
        $this->app->instance(UnsplashService::class, $unsplash);

        Livewire::actingAs($admin)
            ->test(CreateShowcaseRoute::class)
            ->fillForm([
                'departure_iata' => 'GRU',
                'departure_city' => 'São Paulo',
                'arrival_iata' => 'REC',
                'arrival_city' => 'Recife',
                'trip_type' => 'roundtrip',
                'cabin' => 'EC',
                'search_date_from' => '2026-06-01',
                'search_date_to' => '2026-06-15',
                'sample_dates_count' => 4,
                'return_stay_days' => 5,
                'sort_order' => 2,
                'is_active' => true,
                'image_search_query' => 'Praia de Boa Viagem',
                'image_url' => null,
                'image_credit' => null,
                'image_zoom' => 125,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $route = ShowcaseRoute::where('arrival_iata', 'REC')->firstOrFail();

        $this->assertSame('https://img.test/recife.jpg', $route->image_url);
        $this->assertSame('Foto Recife', $route->image_credit);
        Bus::assertDispatched(RefreshShowcaseRoute::class);

        Livewire::actingAs($admin)
            ->test(ListShowcaseRoutes::class)
            ->assertSee('Recife')
            ->callTableAction('refresh', $route);

        Bus::assertDispatched(RefreshShowcaseRoute::class, 2);

        $route->update(['image_url' => null, 'image_credit' => null]);

        Livewire::actingAs($admin)
            ->test(EditShowcaseRoute::class, ['record' => $route->getRouteKey()])
            ->fillForm([
                'departure_iata' => 'GRU',
                'departure_city' => 'São Paulo',
                'arrival_iata' => 'SSA',
                'arrival_city' => 'Salvador',
                'trip_type' => 'oneway',
                'cabin' => 'EX',
                'search_date_from' => '2026-07-01',
                'search_date_to' => '2026-07-08',
                'sample_dates_count' => 2,
                'return_stay_days' => null,
                'sort_order' => 3,
                'is_active' => false,
                'image_search_query' => 'Pelourinho',
                'image_url' => null,
                'image_credit' => null,
                'image_zoom' => 140,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $route->refresh();
        $this->assertSame('SSA', $route->arrival_iata);
        $this->assertSame('https://img.test/salvador.jpg', $route->image_url);

        $log = ShowcaseRefreshLog::create([
            'showcase_route_id' => $route->id,
            'status' => 'failed',
            'dates_searched' => 5,
            'cache_hits' => 2,
            'api_calls' => 3,
            'errors_count' => 1,
            'best_price' => 321.45,
            'best_date' => '2026-07-03',
            'previous_price' => 400,
            'duration_seconds' => 95,
            'error_message' => 'Timeout da API',
            'started_at' => '2026-05-16 10:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ListShowcaseRefreshLogs::class)
            ->assertSee('Salvador')
            ->assertSee('Falhou')
            ->assertSee('Ver erro')
            ->assertTableActionVisible('view_error', $log);
    }

    public function test_issuer_resource_pages_create_edit_and_scope_query(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customerUser = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(CreateIssuer::class)
            ->fillForm([
                'name' => 'Emissor Novo',
                'email' => 'emissor-novo@example.com',
                'password' => 'senha-forte',
                'pushover_user_key' => 'pushover-key',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $issuer = User::where('email', 'emissor-novo@example.com')->firstOrFail();
        $this->assertSame('issuer', $issuer->role);
        $this->assertTrue(Hash::check('senha-forte', $issuer->password));

        Livewire::actingAs($admin)
            ->test(ListIssuers::class)
            ->assertSee('Emissor Novo')
            ->assertDontSee($customerUser->email);

        Livewire::actingAs($admin)
            ->test(EditIssuer::class, ['record' => $issuer->getRouteKey()])
            ->fillForm([
                'name' => 'Emissor Editado',
                'email' => 'emissor-editado@example.com',
                'password' => '',
                'pushover_user_key' => null,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $issuer->id,
            'name' => 'Emissor Editado',
            'email' => 'emissor-editado@example.com',
            'role' => 'issuer',
            'is_active' => false,
        ]);

        $this->actingAs($admin);
        $this->assertEquals([$issuer->id], IssuerResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame(['role' => 'issuer'], IssuerResource::mutateFormDataBeforeCreate([]));
    }

    public function test_referral_resource_pages_render_referral_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $affiliate = $this->createCustomer([
            'name' => 'Afiliado',
            'email' => 'afiliado@example.com',
            'document' => '11144477735',
            'is_affiliate' => true,
            'referral_code' => 'IND-AFILIADO',
        ]);
        $referred = $this->createCustomer([
            'name' => 'Cliente Indicado',
            'email' => 'indicado@example.com',
            'document' => '52998224725',
        ]);
        $order = $this->createOrder([
            'customer_id' => $referred->id,
            'tracking_code' => 'VDP-REF1',
        ]);

        $referral = Referral::create([
            'affiliate_id' => $affiliate->id,
            'referred_order_id' => $order->id,
            'referred_customer_id' => $referred->id,
            'referred_document' => '52998224725',
            'referral_code_used' => 'IND-AFILIADO',
            'order_base_total' => 500,
            'discount_pct' => 10,
            'discount_amount' => 50,
            'credit_pct' => 5,
            'credit_amount' => 25,
            'credit_status' => 'available',
            'credit_available_at' => '2026-05-16 10:00:00',
            'credit_released_at' => '2026-05-16 11:00:00',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(ListReferrals::class)
            ->assertSee('Afiliado')
            ->assertSee('VDP-REF1')
            ->assertSee('Disponível');

        Livewire::actingAs($admin)
            ->test(ViewReferral::class, ['record' => $referral->getRouteKey()])
            ->assertSee('IND-AFILIADO')
            ->assertSee('VDP-REF1')
            ->assertSee('Valores');
    }

    public function test_order_resource_view_renders_full_infolist_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = $this->createCustomer([
            'name' => 'Cliente Pedido',
            'email' => 'cliente-pedido@example.com',
            'document' => '52998224725',
            'phone' => '31999990000',
        ]);
        $coupon = Coupon::create([
            'code' => 'ORDER10',
            'type' => 'percent',
            'value' => 10,
            'max_discount' => 50,
            'active' => true,
        ]);
        $search = $this->createFlightSearch([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'REC',
            'outbound_date' => '2026-06-10',
            'inbound_date' => '2026-06-15',
            'trip_type' => 'roundtrip',
        ]);
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'flight_search_id' => $search->id,
            'tracking_code' => 'VDP-ORD1',
            'total_adults' => 1,
            'total_children' => 1,
            'total_babies' => 1,
            'departure_iata' => 'GRU',
            'arrival_iata' => 'REC',
            'cabin' => 'EX',
            'status' => 'completed',
            'loc' => 'ABC123 / DEF456',
            'discount_amount' => 40,
            'device_type' => 'mobile',
            'paid_at' => '2026-05-16 10:00:00',
            'user_id' => 'bot-user',
            'conversation_id' => 'bot-conversation',
        ]);
        $this->addPassenger($order, [
            'nationality' => 'US',
            'full_name' => 'Passageiro Internacional',
            'document' => '529.982.247-25',
            'passport_number' => 'AB123456',
            'passport_expiry' => '2030-01-01',
            'email' => 'passageiro@example.com',
            'phone' => '31988887777',
        ]);
        $this->addFlight($order, [
            'direction' => 'outbound',
            'cia' => 'LATAM',
            'operator' => 'LATAM',
            'flight_number' => 'LA1234',
            'departure_location' => 'GRU',
            'arrival_location' => 'REC',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'money_price' => '200.00',
            'tax' => '50.00',
            'price_miles' => '12000',
            'loc' => 'ABC123',
            'provider' => 'LATAM Crawler',
            'pricing_type' => 'milhas',
            'connection' => [
                [
                    'DEPARTURE_TIME' => '08:00',
                    'ARRIVAL_TIME' => '09:30',
                    'DEPARTURE_LOCATION' => 'GRU',
                    'ARRIVAL_LOCATION' => 'BSB',
                    'FLIGHT_NUMBER' => 'LA1000',
                    'FLIGHT_DURATION' => '01:30',
                    'TIME_WAITING' => '01:00',
                ],
                [
                    'DEPARTURE_TIME' => '10:30',
                    'ARRIVAL_TIME' => '12:00',
                    'DEPARTURE_LOCATION' => 'BSB',
                    'ARRIVAL_LOCATION' => 'REC',
                    'FLIGHT_NUMBER' => 'LA2000',
                    'FLIGHT_DURATION' => '01:30',
                ],
            ],
        ]);
        $this->addFlight($order, [
            'direction' => 'inbound',
            'cia' => 'GOL',
            'operator' => 'GOL',
            'flight_number' => 'G31234',
            'departure_location' => 'REC',
            'arrival_location' => 'GRU',
            'money_price' => '180.00',
            'tax' => '45.00',
            'price_miles' => '0',
            'loc' => 'DEF456',
            'provider' => 'VDP',
            'pricing_type' => 'convencional',
            'connection' => null,
        ]);
        $this->addPayment($order, [
            'gateway' => 'appmax',
            'status' => 'paid',
            'payment_method' => 'credit_card',
            'amount' => 410,
            'external_checkout_id' => 'appmax-123',
            'paid_at' => '2026-05-16 10:00:00',
        ]);
        $order->statusHistories()->create([
            'status' => 'completed',
            'description' => 'Pedido emitido manualmente',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('VDP-ORD1')
            ->assertSee('ABC123 / DEF456')
            ->assertSee('Passageiro Internacional')
            ->assertSee('AB123456')
            ->assertSee('ORDER10')
            ->assertSee('LATAM Crawler')
            ->assertSee('Cartão de crédito')
            ->assertSee('Pedido emitido manualmente')
            ->assertSee('bot-conversation');

        $this->assertFalse(OrderResource::canCreate());
        $this->assertSame('Aguardando Pagamento', OrderResource::statusLabel('awaiting_payment'));
        $this->assertSame('Estados Unidos', OrderResource::nationalityLabel('US'));
        $this->assertSame('-', OrderResource::nationalityLabel(null));
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '52998224725',
            'status' => 'active',
        ], $attributes));
    }
}
