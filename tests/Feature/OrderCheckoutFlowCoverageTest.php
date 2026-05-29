<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SavedPassenger;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\PaymentGatewayResolver;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class OrderCheckoutFlowCoverageTest extends TestCase
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

    public function test_checkout_summary_and_passenger_pages_render_context_and_not_found_states(): void
    {
        Setting::set('pix_enabled', false, 'boolean');
        Setting::set('credit_card_enabled', true, 'boolean');
        Setting::set('referral_enabled', true, 'boolean');
        Setting::set('max_installments', 6, 'integer');
        Setting::set('interest_rates', [2 => 4.5], 'json');

        $customer = Customer::create([
            'name' => 'Cliente Afiliado',
            'email' => 'cliente@example.com',
            'document' => '52998224725',
            'status' => 'active',
            'is_affiliate' => true,
            'referral_code' => 'IND-CLI123',
        ]);
        SavedPassenger::create([
            'customer_id' => $customer->id,
            'full_name' => 'Maria Silva',
            'nationality' => 'BR',
            'document' => '52998224725',
            'birth_date' => '1990-01-01',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 75,
            'balance_after' => 75,
            'description' => 'Credito inicial',
        ]);

        $order = $this->createOrder();
        $this->addFlight($order, [
            'baggage' => [
                'fare' => 'LIGHT',
                'personal_item' => ['included' => true, 'quantity' => 1, 'weight' => '10kg'],
                'carry_on' => ['included' => true, 'quantity' => 1, 'weight' => '10kg'],
                'checked' => ['included' => false, 'quantity' => 0, 'weight' => null],
            ],
        ]);

        $this->get("/r/{$order->token}")
            ->assertOk()
            ->assertViewIs('checkout.resumo')
            ->assertViewHas('order', fn (Order $viewOrder): bool => $viewOrder->is($order))
            ->assertViewHas('pixEnabled', false)
            ->assertSee('Mala de mao inclusa', false)
            ->assertSee('Mala despachada nao inclusa', false)
            ->assertSee('Voo de ida', false)
            ->assertSee('data-step-state="current"', false)
            ->assertSee('text-xs font-semibold text-gray-600 uppercase tracking-wide">IDA', false)
            ->assertDontSee('bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded">IDA', false);

        $this->actingAs($customer, 'customer')
            ->withCookie('ref_code', 'IND-COOKIE')
            ->get("/r/{$order->token}/passageiros")
            ->assertOk()
            ->assertViewIs('checkout.passengers')
            ->assertViewHas('pixEnabled', false)
            ->assertViewHas('creditCardEnabled', true)
            ->assertViewHas('maxInstallments', 6)
            ->assertViewHas('interestRates', [2 => 4.5])
            ->assertViewHas('walletBalance', 75.0)
            ->assertViewHas('isAffiliate', true)
            ->assertViewHas('refCookie', 'IND-COOKIE')
            ->assertViewHas('savedPassengers', fn ($passengers): bool => $passengers->count() === 1)
            ->assertSee('Mala de mao inclusa', false)
            ->assertSee('Voo de ida', false)
            ->assertSee('data-step-state="completed"', false)
            ->assertSee('data-step-state="current"', false)
            ->assertSee('required-label block text-sm font-medium text-gray-700 mb-1">Nome completo', false)
            ->assertSee('required-label block text-sm font-medium text-gray-700 mb-1">CPF', false)
            ->assertSee('required-label block text-sm font-medium text-gray-700 mb-1">Número do cartão', false);

        $flightSearch = $this->createFlightSearch([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'outbound_date' => '2026-06-10',
            'inbound_date' => '2026-06-17',
            'trip_type' => 'roundtrip',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
        ]);
        $expiredWithSearch = $this->createOrder([
            'flight_search_id' => $flightSearch->id,
            'expires_at' => now()->subMinute(),
        ]);
        $refreshUrl = route('search.results', [
            'trip_type' => 'roundtrip',
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
            'inbound_date' => '2026-06-17',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'EC',
        ]);

        $this->get("/r/{$expiredWithSearch->token}")
            ->assertRedirect($refreshUrl)
            ->assertSessionHas('search_refresh_modal');

        $this->get("/r/{$expiredWithSearch->token}/passageiros")
            ->assertRedirect($refreshUrl)
            ->assertSessionHas('search_refresh_modal');

        $expired = $this->createOrder([
            'expires_at' => now()->subMinute(),
        ]);

        $this->get("/r/{$expired->token}")
            ->assertNotFound()
            ->assertViewIs('checkout.not-found');

        $this->get("/r/{$expired->token}/passageiros")
            ->assertNotFound()
            ->assertViewIs('checkout.not-found');

        foreach (['cancelled', 'awaiting_payment', 'awaiting_emission', 'completed'] as $status) {
            $unavailable = $this->createOrder([
                'status' => $status,
                'expires_at' => now()->addHour(),
            ]);

            $this->get("/r/{$unavailable->token}")
                ->assertNotFound()
                ->assertViewIs('checkout.not-found');

            $this->get("/r/{$unavailable->token}/passageiros")
                ->assertNotFound()
                ->assertViewIs('checkout.not-found');
        }
    }

    public function test_checkout_success_marks_all_steps_completed_and_separates_emission_status(): void
    {
        $order = $this->createOrder(['status' => 'awaiting_emission']);
        $this->addFlight($order);

        $html = view('checkout.success', [
            'order' => $order->load(['flights', 'flightSearch']),
        ])->render();

        $this->assertSame(3, substr_count($html, 'data-step-state="completed"'));
        $this->assertStringContainsString('Pagamento confirmado!', $html);
        $this->assertStringContainsString('Aguardando emissão', $html);
    }

    public function test_payment_callback_covers_terminal_missing_expired_exception_paid_and_failed_states(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $cancelledOrder = $this->createOrder(['status' => 'cancelled']);
        $this->get(route('checkout.payment-callback', $cancelledOrder))
            ->assertOk()
            ->assertViewIs('checkout.cancelled');

        $completedOrder = $this->createOrder(['status' => 'completed']);
        $this->get(route('checkout.payment-callback', $completedOrder))
            ->assertRedirect(route('tracking.show', $completedOrder->tracking_code))
            ->assertSessionHas("tracking_verified_{$completedOrder->tracking_code}", true);

        $pendingOrder = $this->createOrder(['status' => 'pending']);
        $this->get(route('checkout.payment-callback', $pendingOrder))
            ->assertNotFound()
            ->assertViewIs('checkout.not-found');

        $noPaymentOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $this->get(route('checkout.payment-callback', $noPaymentOrder))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment')
            ->assertViewHas('payment', null);

        $cardOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $cardPayment = $this->addPayment($cardOrder, [
            'payment_method' => 'credit_card',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::on(fn ($payment): bool => $payment->is($cardPayment)))
            ->andThrow(new \RuntimeException('operadora em análise'));
        $this->get(route('checkout.payment-callback', $cardOrder))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment')
            ->assertSee('Pagamento em análise')
            ->assertDontSee('Aguardando pagamento')
            ->assertDontSee('Verificar pagamento');

        $expiredOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $expiredPayment = $this->addPayment($expiredOrder, [
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
        $this->get(route('checkout.payment-callback', $expiredOrder))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');
        $this->assertDatabaseHas('order_payments', [
            'id' => $expiredPayment->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $expiredOrder->id,
            'status' => 'cancelled',
        ]);

        $exceptionOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $exceptionPayment = $this->addPayment($exceptionOrder, [
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::on(fn ($payment): bool => $payment->is($exceptionPayment)))
            ->andThrow(new \RuntimeException('gateway sem resposta'));

        $paidOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $paidPayment = $this->addPayment($paidOrder, [
            'status' => 'pending',
            'gateway_response' => [
                'payment_method' => 'pix',
                'payment_id' => 'pay-123',
            ],
            'expires_at' => now()->addHour(),
        ]);
        $failedOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $failedPayment = $this->addPayment($failedOrder, [
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $paidGateway = Mockery::mock(PaymentGatewayInterface::class);
        $paidGateway->shouldReceive('getCheckoutStatus')
            ->once()
            ->with(Mockery::on(fn ($payment): bool => $payment->is($paidPayment)))
            ->andReturn('paid');
        $failedGateway = Mockery::mock(PaymentGatewayInterface::class);
        $failedGateway->shouldReceive('getCheckoutStatus')
            ->once()
            ->with(Mockery::on(fn ($payment): bool => $payment->is($failedPayment)))
            ->andReturn('failed');

        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::on(fn ($payment): bool => $payment->is($paidPayment)))
            ->andReturn($paidGateway);
        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::on(fn ($payment): bool => $payment->is($failedPayment)))
            ->andReturn($failedGateway);
        $this->get(route('checkout.payment-callback', $exceptionOrder))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');

        $this->get(route('checkout.payment-callback', $paidOrder))
            ->assertRedirect(route('tracking.show', $paidOrder->tracking_code))
            ->assertSessionHas("tracking_verified_{$paidOrder->tracking_code}", true);

        $this->assertDatabaseHas('order_payments', [
            'id' => $paidPayment->id,
            'status' => 'paid',
            'payment_method' => 'pix',
            'external_payment_id' => 'pay-123',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $paidOrder->id,
            'status' => 'awaiting_emission',
        ]);

        $this->get(route('checkout.payment-callback', $failedOrder))
            ->assertOk()
            ->assertViewIs('checkout.cancelled');

        $this->assertDatabaseHas('order_payments', [
            'id' => $failedPayment->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $failedOrder->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_store_rejects_payment_method_when_all_checkout_gateways_are_disabled(): void
    {
        Setting::set('gateway_pix', '', 'string');
        Setting::set('gateway_credit_card', '', 'string');
        Setting::set('pix_enabled', false, 'boolean');
        Setting::set('credit_card_enabled', false, 'boolean');

        $order = $this->createOrder();
        $this->addFlight($order);

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldNotReceive('resolveForMethod');
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->from(route('checkout.passengers', $order->token))
            ->post("/r/{$order->token}", $this->validCheckoutPayload([
                'payment_method' => 'pix',
            ]))
            ->assertRedirect(route('checkout.passengers', $order->token))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('order_passengers', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_store_saves_logged_customer_passenger_and_falls_back_when_gateway_creation_fails(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Logado',
            'email' => 'logado@example.com',
            'document' => '52998224725',
            'status' => 'active',
        ]);
        $order = $this->createOrder();
        $this->addFlight($order);

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForMethod')
            ->once()
            ->with('pix')
            ->andThrow(new \RuntimeException('gateway indisponivel'));
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->actingAs($customer, 'customer')
            ->post("/r/{$order->token}", $this->validCheckoutPayload([
                'passengers' => [
                    [
                        'nationality' => 'BR',
                        'full_name' => 'Maria Silva Atualizada',
                        'document' => '529.982.247-25',
                        'birth_date' => '01/01/1990',
                        'email' => 'maria.nova@example.com',
                        'phone' => '31999999999',
                        'save_passenger' => '1',
                    ],
                ],
            ]))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');

        $this->assertDatabaseHas('saved_passengers', [
            'customer_id' => $customer->id,
            'document' => '52998224725',
            'full_name' => 'Maria Silva Atualizada',
            'email' => 'maria.nova@example.com',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'awaiting_payment',
        ]);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_store_saves_foreign_passenger_by_passport_for_logged_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Internacional',
            'email' => 'internacional@example.com',
            'document' => '52998224725',
            'status' => 'active',
        ]);
        $order = $this->createOrder([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'DXB',
        ]);
        $this->addFlight($order, [
            'arrival_location' => 'DXB',
            'arrival_label' => 'Dubai (DXB)',
        ]);

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForMethod')
            ->once()
            ->with('pix')
            ->andThrow(new \RuntimeException('gateway indisponivel'));
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->actingAs($customer, 'customer')
            ->post("/r/{$order->token}", $this->validCheckoutPayload([
                'passengers' => [
                    [
                        'nationality' => 'US',
                        'full_name' => 'John Smith',
                        'document' => '',
                        'passport_number' => 'US123456',
                        'passport_expiry' => '31/12/2030',
                        'birth_date' => '10/10/1990',
                        'email' => 'john@example.com',
                        'phone' => '11999999998',
                        'save_passenger' => '1',
                    ],
                ],
            ]))
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');

        $this->assertDatabaseHas('saved_passengers', [
            'customer_id' => $customer->id,
            'nationality' => 'US',
            'passport_number' => 'US123456',
            'document' => null,
            'full_name' => 'John Smith',
        ]);
        $this->assertDatabaseHas('order_passengers', [
            'order_id' => $order->id,
            'nationality' => 'US',
            'passport_number' => 'US123456',
            'document' => null,
        ]);

        $saved = SavedPassenger::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('2030-12-31', $saved->passport_expiry->format('Y-m-d'));
        $this->assertSame('2030-12-31', $order->passengers()->firstOrFail()->passport_expiry->format('Y-m-d'));
    }

    public function test_apply_coupon_covers_missing_expired_referral_self_use_and_exception_paths(): void
    {
        $order = $this->createOrder();
        $this->addFlight($order);

        $this->postJson('/r/token-inexistente/apply-coupon', [
            'coupon_code' => 'SAVE',
        ])
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Pedido não encontrado.',
            ]);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => '   ',
        ])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Informe o código do cupom ou indicação.',
            ]);

        Coupon::create([
            'code' => 'OLD',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => 'OLD',
            'payer_document' => '529.982.247-25',
        ])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Cupom inválido ou expirado.',
            ]);

        Setting::set('referral_enabled', true, 'boolean');
        Setting::set('referral_discount_pct', '10');
        $affiliate = Customer::create([
            'name' => 'Afiliado Forte',
            'email' => 'afiliado@example.com',
            'document' => '11144477735',
            'status' => 'active',
            'is_affiliate' => true,
            'referral_code' => 'IND-AFF456',
        ]);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => 'aff456',
            'payer_document' => '529.982.247-25',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'referral',
                'discount_amount' => 10,
                'new_total' => 120,
                'message' => 'Desconto de indicação de Afiliado aplicado!',
            ]);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => $affiliate->referral_code,
            'payer_document' => '111.444.777-35',
        ])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Você não pode usar seu próprio código de indicação.',
            ]);
    }

    public function test_apply_coupon_returns_generic_error_when_resolution_throws(): void
    {
        $order = $this->createOrder();
        $this->addFlight($order);

        $referralService = Mockery::mock(ReferralService::class);
        $referralService->shouldReceive('resolveCode')
            ->once()
            ->with('ERROR')
            ->andThrow(new \RuntimeException('erro calculado'));
        $this->app->instance(ReferralService::class, $referralService);

        $this->postJson("/r/{$order->token}/apply-coupon", [
            'coupon_code' => 'ERROR',
        ])
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Não foi possível validar o código agora. Tente novamente em instantes.',
            ]);
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
}
