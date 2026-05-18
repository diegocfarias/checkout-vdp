<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class PaymentStatusControllerTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payment_callback_marks_paid_payment_and_redirects_to_tracking(): void
    {
        Carbon::setTestNow('2026-05-14 12:00:00');
        $order = $this->createOrder(['status' => 'awaiting_payment']);
        $payment = $this->addPayment($order, [
            'gateway' => 'appmax',
            'external_checkout_id' => '123',
            'status' => 'pending',
            'gateway_response' => [
                'payment_id' => 'tx-123',
                'payment_method' => 'pix',
            ],
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('getCheckoutStatus')
            ->once()
            ->with(Mockery::on(fn ($received): bool => $received->is($payment)))
            ->andReturn('paid');

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::type(\App\Models\OrderPayment::class))
            ->andReturn($gateway);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->get("/r/{$order->token}/payment-callback")
            ->assertRedirect(route('tracking.show', $order->tracking_code));

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'payment_method' => 'pix',
            'external_payment_id' => 'tx-123',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_emission',
        ]);
        $this->assertTrue($order->fresh()->paid_at->equalTo(Carbon::parse('2026-05-14 12:00:00')));
    }

    public function test_payment_callback_expires_pending_payment_without_calling_gateway(): void
    {
        $order = $this->createOrder(['status' => 'awaiting_payment']);
        $payment = $this->addPayment($order, [
            'gateway' => 'appmax',
            'external_checkout_id' => '123',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->get("/r/{$order->token}/payment-callback")
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_payment_callback_handles_cancelled_and_missing_payment_states(): void
    {
        $cancelledOrder = $this->createOrder(['status' => 'cancelled']);

        $this->get("/r/{$cancelledOrder->token}/payment-callback")
            ->assertOk()
            ->assertViewIs('checkout.cancelled');

        $withoutPayment = $this->createOrder(['status' => 'awaiting_payment']);

        $this->get("/r/{$withoutPayment->token}/payment-callback")
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment')
            ->assertViewHas('payment', null);
    }

    public function test_payment_callback_keeps_order_pending_when_gateway_lookup_fails(): void
    {
        $gatewayErrorOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $gatewayErrorPayment = $this->addPayment($gatewayErrorOrder, [
            'gateway' => 'appmax',
            'external_checkout_id' => 'gateway-error',
            'status' => 'pending',
        ]);
        $errorGateway = Mockery::mock(PaymentGatewayInterface::class);
        $errorGateway->shouldReceive('getCheckoutStatus')
            ->once()
            ->with(Mockery::on(fn ($received): bool => $received->is($gatewayErrorPayment)))
            ->andThrow(new \RuntimeException('gateway offline'));

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::on(fn ($received): bool => $received->is($gatewayErrorPayment)))
            ->andReturn($errorGateway);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->get("/r/{$gatewayErrorOrder->token}/payment-callback")
            ->assertOk()
            ->assertViewIs('checkout.awaiting-payment');

        $this->assertDatabaseHas('order_payments', [
            'id' => $gatewayErrorPayment->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $gatewayErrorOrder->id,
            'status' => 'awaiting_payment',
        ]);
    }

    public function test_payment_callback_cancels_failed_gateway_status(): void
    {
        $failedOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $failedPayment = $this->addPayment($failedOrder, [
            'gateway' => 'appmax',
            'external_checkout_id' => 'failed-status',
            'status' => 'pending',
        ]);

        $failedGateway = Mockery::mock(PaymentGatewayInterface::class);
        $failedGateway->shouldReceive('getCheckoutStatus')
            ->once()
            ->with(Mockery::on(fn ($received): bool => $received->is($failedPayment)))
            ->andReturn('failed');

        $resolver = Mockery::mock(PaymentGatewayResolver::class);
        $resolver->shouldReceive('resolveForPayment')
            ->once()
            ->with(Mockery::on(fn ($received): bool => $received->is($failedPayment)))
            ->andReturn($failedGateway);
        $this->app->instance(PaymentGatewayResolver::class, $resolver);

        $this->get("/r/{$failedOrder->token}/payment-callback")
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

    public function test_appmax_webhook_marks_payment_as_paid(): void
    {
        Carbon::setTestNow('2026-05-14 13:00:00');
        $order = $this->createOrder(['status' => 'awaiting_payment']);
        $payment = $this->addPayment($order, [
            'gateway' => 'appmax',
            'external_checkout_id' => '987',
            'status' => 'pending',
            'payment_method' => 'pix',
        ]);

        $this->postJson(route('webhooks.appmax'), [
            'event' => 'order_paid',
            'event_type' => 'order',
            'data' => [
                'order_id' => 987,
                'status' => 'aprovado',
                'payment_method' => 'credit_card',
                'transaction_id' => 'tx-appmax',
            ],
        ])
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'payment_method' => 'credit_card',
            'external_payment_id' => 'tx-appmax',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_emission',
        ]);
        $this->assertTrue($payment->fresh()->paid_at->equalTo(Carbon::parse('2026-05-14 13:00:00')));
    }

    public function test_appmax_webhook_cancels_or_refunds_matching_payment(): void
    {
        $cancelledOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $cancelledPayment = $this->addPayment($cancelledOrder, [
            'gateway' => 'appmax',
            'external_checkout_id' => '111',
            'status' => 'pending',
        ]);
        $refundedOrder = $this->createOrder(['status' => 'awaiting_emission']);
        $refundedPayment = $this->addPayment($refundedOrder, [
            'gateway' => 'appmax',
            'external_checkout_id' => '222',
            'status' => 'paid',
        ]);

        $this->postJson(route('webhooks.appmax'), [
            'event' => 'payment_not_authorized',
            'data' => ['order_id' => 111],
        ])->assertOk();
        $this->postJson(route('webhooks.appmax'), [
            'event' => 'order_refunded',
            'data' => ['order_id' => 222],
        ])->assertOk();

        $this->assertDatabaseHas('order_payments', [
            'id' => $cancelledPayment->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $cancelledOrder->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $refundedPayment->id,
            'status' => 'refunded',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $refundedOrder->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_abacatepay_webhook_marks_payment_as_paid_or_cancelled(): void
    {
        Carbon::setTestNow('2026-05-14 14:00:00');
        $paidOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $paidPayment = $this->addPayment($paidOrder, [
            'gateway' => 'abacatepay',
            'external_checkout_id' => 'pix-paid',
            'status' => 'pending',
        ]);
        $cancelledOrder = $this->createOrder(['status' => 'awaiting_payment']);
        $cancelledPayment = $this->addPayment($cancelledOrder, [
            'gateway' => 'abacatepay',
            'external_checkout_id' => 'pix-expired',
            'status' => 'pending',
        ]);

        $this->postJson(route('webhooks.abacatepay'), [
            'event' => 'billing.paid',
            'data' => [
                'id' => 'pix-paid',
                'status' => 'PAID',
            ],
        ])->assertOk()->assertJson(['received' => true]);

        $this->postJson(route('webhooks.abacatepay'), [
            'event' => 'billing.failed',
            'data' => [
                'id' => 'pix-expired',
                'status' => 'EXPIRED',
            ],
        ])->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('order_payments', [
            'id' => $paidPayment->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $paidOrder->id,
            'status' => 'awaiting_emission',
        ]);
        $this->assertTrue($paidPayment->fresh()->paid_at->equalTo(Carbon::parse('2026-05-14 14:00:00')));

        $this->assertDatabaseHas('order_payments', [
            'id' => $cancelledPayment->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $cancelledOrder->id,
            'status' => 'cancelled',
        ]);
    }
}
