<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\PaymentGatewayResolver;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class OrderCheckoutControllerTest extends TestCase
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
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_apply_coupon_returns_discount_preview_for_valid_coupon(): void
    {
        $order = $this->createOrder([
            'total_adults' => 2,
            'total_children' => 0,
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);
        $coupon = Coupon::create([
            'code' => 'SAVE50',
            'type' => 'fixed',
            'value' => 50,
            'active' => true,
            'cumulative_with_pix' => true,
        ]);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => ' save50 ',
            'payer_document' => '529.982.247-25',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'coupon',
                'coupon_code' => $coupon->code,
                'discount_amount' => 50,
                'new_total' => 210,
                'cumulative_with_pix' => true,
                'message' => 'Cupom aplicado!',
            ]);
    }

    public function test_apply_coupon_rejects_unknown_or_restricted_coupon(): void
    {
        $order = $this->createOrder();
        $this->addFlight($order);

        $restricted = Coupon::create([
            'code' => 'VIP',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ]);
        $restricted->customers()->attach(Customer::create([
            'name' => 'Cliente VIP',
            'email' => 'vip@example.com',
            'document' => '111.444.777-35',
            'status' => 'active',
        ]));

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => 'UNKNOWN',
            'payer_document' => '529.982.247-25',
        ])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Código inválido ou expirado.',
            ]);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => 'VIP',
            'payer_document' => '529.982.247-25',
        ])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Este cupom não está disponível para você.',
            ]);
    }

    public function test_store_creates_passenger_customer_coupon_and_pix_payment_with_discounted_total(): void
    {
        Setting::set('pix_discount', '10');
        $order = $this->createOrder([
            'total_adults' => 1,
            'total_children' => 0,
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);
        $coupon = Coupon::create([
            'code' => 'SAVE30',
            'type' => 'fixed',
            'value' => 30,
            'active' => true,
            'cumulative_with_pix' => true,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createCheckout')
            ->once()
            ->with(
                Mockery::on(fn ($checkoutOrder): bool => $checkoutOrder->is($order)),
                'pix',
                Mockery::on(function (array $cardData): bool {
                    return abs($cardData['total_with_interest'] - 90) < 0.01
                        && $cardData['payer']['document'] === '52998224725'
                        && $cardData['payer']['email'] === 'pagador@example.com';
                }),
            )
            ->andReturnUsing(fn ($checkoutOrder) => $checkoutOrder->payments()->create([
                'gateway' => 'fake',
                'external_checkout_id' => 'fake-pix',
                'payment_url' => '000201PIX',
                'status' => 'pending',
                'payment_method' => 'pix',
                'amount' => 90,
                'currency' => 'BRL',
            ]));

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForMethod')->once()->with('pix')->andReturn($gateway);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->post("/r/{$order->token}", $this->validCheckoutPayload([
            'coupon_code' => 'SAVE30',
        ]))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');

        $this->assertDatabaseHas('order_passengers', [
            'order_id' => $order->id,
            'full_name' => 'Maria Silva',
            'document' => '529.982.247-25',
        ]);
        $this->assertDatabaseHas('customers', [
            'email' => 'pagador@example.com',
            'document' => '52998224725',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'coupon_id' => $coupon->id,
            'discount_amount' => 30,
            'status' => 'awaiting_payment',
        ]);
        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'usage_count' => 1,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'gateway' => 'fake',
            'amount' => 90,
            'payment_method' => 'pix',
        ]);
    }

    public function test_store_marks_order_as_paid_when_wallet_covers_total(): void
    {
        Carbon::setTestNow('2026-05-14 11:00:00');
        $customer = Customer::create([
            'name' => 'Cliente Wallet',
            'email' => 'wallet@example.com',
            'document' => '529.982.247-25',
            'status' => 'active',
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 200,
            'balance_after' => 200,
            'description' => 'Credito inicial',
        ]);

        $order = $this->createOrder([
            'total_adults' => 1,
            'total_children' => 0,
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);

        $this->actingAs($customer, 'customer')
            ->post("/r/{$order->token}", $this->validCheckoutPayload([
                'use_wallet' => '1',
            ]))
            ->assertRedirect(route('tracking.show', $order->tracking_code));

        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => 'debit',
            'amount' => 130,
            'balance_after' => 70,
            'order_id' => $order->id,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'gateway' => 'wallet',
            'status' => 'paid',
            'payment_method' => 'wallet',
            'amount' => 130,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'awaiting_emission',
            'wallet_amount_used' => 130,
        ]);
        $this->assertTrue($order->fresh()->paid_at->equalTo(Carbon::parse('2026-05-14 11:00:00')));
    }

    public function test_store_applies_referral_discount_and_credit_card_interest(): void
    {
        Setting::set('referral_enabled', true, 'boolean');
        Setting::set('referral_discount_pct', '10');
        Setting::set('referral_credit_pct', '5');
        Setting::set('gateway_credit_card', 'c6bank');
        Setting::set('interest_rates_c6bank', [2 => 10], 'json');

        $affiliate = Customer::create([
            'name' => 'Afiliado Teste',
            'email' => 'afiliado@example.com',
            'document' => '11144477735',
            'status' => 'active',
            'is_affiliate' => true,
            'referral_code' => 'IND-AFF123',
        ]);
        $order = $this->createOrder([
            'total_adults' => 1,
            'total_children' => 0,
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createCheckout')
            ->once()
            ->with(
                Mockery::on(fn ($checkoutOrder): bool => $checkoutOrder->is($order)),
                'credit_card',
                Mockery::on(function (array $cardData): bool {
                    return abs($cardData['total_with_interest'] - 128.70) < 0.01
                        && $cardData['installments'] === '2'
                        && $cardData['payer']['document'] === '52998224725'
                        && $cardData['payer']['billing']['zipcode'] === '30140071';
                }),
            )
            ->andReturnUsing(fn ($checkoutOrder) => $checkoutOrder->payments()->create([
                'gateway' => 'fake',
                'external_checkout_id' => 'fake-card',
                'payment_url' => 'https://pay.test/checkout',
                'status' => 'pending',
                'payment_method' => 'credit_card',
                'amount' => 128.70,
                'currency' => 'BRL',
            ]));

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForMethod')->once()->with('credit_card')->andReturn($gateway);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->post("/r/{$order->token}", $this->creditCardPayload([
            'coupon_code' => 'IND-AFF123',
        ]))
            ->assertRedirect('https://pay.test/checkout');

        $customer = Customer::where('email', 'pagador@example.com')->firstOrFail();

        $this->assertDatabaseHas('referrals', [
            'affiliate_id' => $affiliate->id,
            'referred_order_id' => $order->id,
            'referred_customer_id' => $customer->id,
            'referral_code_used' => 'IND-AFF123',
            'discount_amount' => 13,
            'credit_amount' => 6.5,
            'credit_status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'discount_amount' => 13,
            'status' => 'awaiting_payment',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 128.70,
            'payment_method' => 'credit_card',
        ]);
    }

    public function test_store_shows_price_changed_view_and_updates_flight_snapshot(): void
    {
        $search = $this->createFlightSearch([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'outbound_date' => '2026-06-10',
        ]);
        $order = $this->createOrder([
            'flight_search_id' => $search->id,
            'total_adults' => 1,
            'total_children' => 0,
        ]);
        $flight = $this->addFlight($order, [
            'operator' => 'GOL',
            'price_money' => '100,00',
            'price_miles' => '0',
            'boarding_tax' => '30,00',
            'money_price' => '100.00',
            'tax' => '30.00',
            'unique_id' => 'price-change-flight',
        ]);

        $vdp = Mockery::mock(VdpFlightService::class)->makePartial();
        $vdp->shouldReceive('revalidateFlightPair')
            ->once()
            ->andReturn([
                'outbound' => [
                    'operator' => 'GOL',
                    'flight_number' => 'G31234',
                    'departure_time' => '10:00',
                    'arrival_time' => '11:00',
                    'departure_location' => 'GRU',
                    'arrival_location' => 'SDU',
                    'boarding_tax' => '30,00',
                    'price_money' => '150,00',
                    'price_miles' => '0',
                    'unique_id' => 'price-change-flight',
                ],
                'inbound' => null,
            ]);
        $this->app->instance(VdpFlightService::class, $vdp);

        $this->post("/r/{$order->token}", $this->validCheckoutPayload())
            ->assertOk()
            ->assertViewIs('checkout.price-changed')
            ->assertViewHas('oldTotal', 130.0)
            ->assertViewHas('newTotal', 180.0)
            ->assertViewHas('diff', 50.0);

        $this->assertDatabaseHas('order_flights', [
            'id' => $flight->id,
            'money_price' => '150.00',
            'tax' => '30.00',
        ]);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_store_returns_not_found_for_expired_order(): void
    {
        $order = $this->createOrder([
            'expires_at' => now()->subMinute(),
        ]);

        $this->post("/r/{$order->token}", $this->validCheckoutPayload())
            ->assertNotFound()
            ->assertViewIs('checkout.not-found');
    }

    private function validCheckoutPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'passengers' => [
                [
                    'nationality' => 'BR',
                    'full_name' => 'Maria Silva',
                    'document' => '529.982.247-25',
                    'birth_date' => '1990-01-01',
                    'email' => 'maria@example.com',
                    'phone' => '11999999999',
                ],
            ],
            'payment_method' => 'pix',
            'payer_name' => 'Maria Silva',
            'payer_email' => 'pagador@example.com',
            'payer_document' => '529.982.247-25',
        ], $overrides);
    }

    private function creditCardPayload(array $overrides = []): array
    {
        return array_replace_recursive($this->validCheckoutPayload([
            'payment_method' => 'credit_card',
            'card_number' => '4111111111111111',
            'card_cvv' => '123',
            'card_month' => 12,
            'card_year' => (int) date('y') + 1,
            'card_name' => 'MARIA SILVA',
            'installments' => '2',
            'billing_zipcode' => '30140-071',
            'billing_street' => 'Rua Teste',
            'billing_number' => '123',
            'billing_complement' => 'Apto 1',
            'billing_neighborhood' => 'Centro',
            'billing_city' => 'Belo Horizonte',
            'billing_state' => 'MG',
        ]), $overrides);
    }
}
