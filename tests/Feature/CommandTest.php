<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Jobs\RefreshShowcaseRoute;
use App\Models\Order;
use App\Models\Setting;
use App\Models\ShowcaseRoute;
use App\Services\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class CommandTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.botpress.webhook_url', null);
        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_expire_checkouts_expires_pix_payments_and_expired_orders(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        $pixOrder = $this->createOrder([
            'status' => 'awaiting_payment',
            'expires_at' => now()->addHour(),
        ]);
        $pixPayment = $this->addPayment($pixOrder, [
            'gateway' => 'appmax',
            'status' => 'pending',
            'payment_method' => 'pix',
            'expires_at' => now()->subMinute(),
        ]);
        $expiredOrder = $this->createOrder([
            'status' => 'awaiting_payment',
            'expires_at' => now()->subMinute(),
        ]);
        $cardPayment = $this->addPayment($expiredOrder, [
            'gateway' => 'appmax',
            'status' => 'pending',
            'payment_method' => 'credit_card',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('cancelCheckout')->twice();
        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForPayment')
            ->twice()
            ->with(Mockery::type(\App\Models\OrderPayment::class))
            ->andReturn($gateway);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->artisan('orders:expire-checkouts')
            ->assertSuccessful();

        $this->assertDatabaseHas('order_payments', [
            'id' => $pixPayment->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $pixOrder->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $cardPayment->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $expiredOrder->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_expired_pix_does_not_cancel_order_when_another_payment_is_active(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        $order = $this->createOrder([
            'status' => 'awaiting_payment',
            'expires_at' => now()->addHour(),
        ]);
        $expiredPix = $this->addPayment($order, [
            'gateway' => 'appmax',
            'status' => 'pending',
            'payment_method' => 'pix',
            'expires_at' => now()->subMinute(),
        ]);
        $activeCard = $this->addPayment($order, [
            'gateway' => 'c6bank',
            'status' => 'pending',
            'payment_method' => 'credit_card',
            'expires_at' => null,
        ]);

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldNotReceive('resolveForPayment');
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->artisan('orders:expire-checkouts')
            ->assertSuccessful();

        $this->assertDatabaseHas('order_payments', [
            'id' => $expiredPix->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $activeCard->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_payment',
        ]);
    }

    public function test_refresh_showcase_dispatches_jobs_for_active_routes(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        Bus::fake();
        Setting::set('showcase_refresh_minutes', 60, 'integer');
        Setting::set('showcase_wait_seconds', 1, 'integer');

        $staleRoute = $this->createShowcaseRoute([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'last_refreshed_at' => now()->subMinutes(90),
        ]);
        $freshRoute = $this->createShowcaseRoute([
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
            'last_refreshed_at' => now()->subMinutes(10),
        ]);
        $inactiveRoute = $this->createShowcaseRoute([
            'departure_iata' => 'POA',
            'arrival_iata' => 'REC',
            'is_active' => false,
        ]);

        $this->artisan('showcase:refresh')
            ->expectsOutput('Despachando refresh para 1 rota(s)...')
            ->assertSuccessful();

        Bus::assertDispatched(RefreshShowcaseRoute::class, fn (RefreshShowcaseRoute $job): bool => $job->showcaseRoute->is($staleRoute));
        Bus::assertNotDispatched(RefreshShowcaseRoute::class, fn (RefreshShowcaseRoute $job): bool => $job->showcaseRoute->is($freshRoute));
        Bus::assertNotDispatched(RefreshShowcaseRoute::class, fn (RefreshShowcaseRoute $job): bool => $job->showcaseRoute->is($inactiveRoute));
    }

    public function test_seed_fake_order_command_creates_checkout_fixture(): void
    {
        config()->set('app.url', 'https://checkout.test');

        $this->artisan('orders:seed-fake')
            ->expectsOutput('Pedido fake criado com sucesso!')
            ->assertSuccessful();

        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('GRU', $order->departure_iata);
        $this->assertSame('GIG', $order->arrival_iata);
        $this->assertCount(2, $order->flights);
    }

    private function createShowcaseRoute(array $attributes = []): ShowcaseRoute
    {
        return ShowcaseRoute::create(array_merge([
            'departure_iata' => 'GRU',
            'departure_city' => 'São Paulo',
            'arrival_iata' => 'SDU',
            'arrival_city' => 'Rio de Janeiro',
            'trip_type' => 'roundtrip',
            'cabin' => 'EC',
            'search_window_days' => 30,
            'return_stay_days' => 7,
            'sample_dates_count' => 2,
            'is_active' => true,
        ], $attributes));
    }
}
