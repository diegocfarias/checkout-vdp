<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\C6BankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class C6BankServiceTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        config()->set('app.url', 'https://checkout.test');
        config()->set('services.c6bank.base_url', 'https://c6.test');
        config()->set('services.c6bank.client_id', 'client-id');
        config()->set('services.c6bank.client_secret', 'client-secret');
        config()->set('services.c6bank.api_key', 'api-key');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_create_checkout_posts_payload_and_persists_pix_payment(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        Setting::set('pix_expiration_minutes', 15, 'integer');
        $order = $this->createOrder([
            'total_adults' => 1,
            'total_children' => 1,
            'token' => 'order-token',
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);

        Http::fake([
            'https://c6.test/checkout' => Http::response([
                'id' => 'checkout-123',
                'payment_url' => 'https://pay.test/checkout-123',
            ]),
        ]);

        $payment = (new C6BankService)->createCheckout($order, 'pix');

        $this->assertSame('c6bank', $payment->gateway);
        $this->assertSame('checkout-123', $payment->external_checkout_id);
        $this->assertSame('https://pay.test/checkout-123', $payment->payment_url);
        $this->assertSame('pix', $payment->payment_method);
        $this->assertEqualsWithDelta(260, (float) $payment->amount, 0.01);
        $this->assertTrue($payment->expires_at->equalTo(Carbon::parse('2026-05-14 10:15:00')));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://c6.test/checkout'
                && $request->hasHeader('X-API-Key', 'api-key')
                && $request->hasHeader('Client-Id', 'client-id')
                && $request->hasHeader('Client-Secret', 'client-secret')
                && $request['amount'] === 260.0
                && $request['currency'] === 'BRL'
                && $request['reference'] === 'order-token'
                && $request['return_url'] === 'https://checkout.test/r/order-token/payment-callback';
        });
    }

    public function test_create_checkout_uses_total_with_interest_when_provided(): void
    {
        $order = $this->createOrder();
        $this->addFlight($order, [
            'money_price' => '999.00',
            'tax' => '999.00',
        ]);

        Http::fake([
            'https://c6.test/checkout' => Http::response([
                'checkout_id' => 'checkout-card',
                'url' => 'https://pay.test/card',
            ]),
        ]);

        $payment = (new C6BankService)->createCheckout($order, 'credit_card', [
            'total_with_interest' => 123.45,
        ]);

        $this->assertSame('checkout-card', $payment->external_checkout_id);
        $this->assertSame('credit_card', $payment->payment_method);
        $this->assertEqualsWithDelta(123.45, (float) $payment->amount, 0.01);
        $this->assertNull($payment->expires_at);

        Http::assertSent(fn ($request): bool => $request['amount'] === 123.45);
    }

    public function test_get_checkout_status_maps_gateway_status_and_updates_response(): void
    {
        $payment = $this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => 'checkout-123',
        ]);

        Http::fake([
            'https://c6.test/checkout/checkout-123' => Http::response([
                'status' => 'approved',
                'provider_id' => 'provider-123',
            ]),
        ]);

        $this->assertSame('paid', (new C6BankService)->getCheckoutStatus($payment));
        $this->assertSame('approved', $payment->fresh()->gateway_response['status']);
    }

    public function test_get_checkout_status_returns_pending_without_external_id_or_on_failure(): void
    {
        $withoutExternalId = $this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => null,
        ]);
        $failed = $this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => 'checkout-fail',
        ]);

        Http::fake([
            'https://c6.test/checkout/checkout-fail' => Http::response(['error' => true], 500),
        ]);

        $service = new C6BankService;

        $this->assertSame('pending', $service->getCheckoutStatus($withoutExternalId));
        $this->assertSame('pending', $service->getCheckoutStatus($failed));
    }

    public function test_cancel_checkout_updates_payment_and_stores_gateway_response(): void
    {
        $payment = $this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => 'checkout-123',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://c6.test/checkout/checkout-123' => Http::response([
                'cancelled' => true,
            ]),
        ]);

        (new C6BankService)->cancelCheckout($payment);

        $payment->refresh();
        $this->assertSame('cancelled', $payment->status);
        $this->assertTrue($payment->gateway_response['cancelled']);
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
    }

    public function test_cancel_checkout_without_external_id_only_updates_payment(): void
    {
        $payment = $this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => null,
            'status' => 'pending',
        ]);

        Http::fake();

        (new C6BankService)->cancelCheckout($payment);

        $this->assertSame('cancelled', $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_refund_payment_returns_false_for_manual_or_missing_checkout(): void
    {
        $service = new C6BankService;

        $this->assertFalse($service->refundPayment($this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => null,
        ])));
        $this->assertFalse($service->refundPayment($this->addPayment($this->createOrder(), [
            'gateway' => 'c6bank',
            'external_checkout_id' => 'checkout-123',
        ])));
    }
}
