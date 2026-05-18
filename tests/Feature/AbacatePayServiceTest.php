<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AbacatePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class AbacatePayServiceTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        config()->set('services.abacatepay.api_url', 'https://abacate.test/v1');
        config()->set('services.abacatepay.api_key', 'abacate-key');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_create_checkout_posts_pix_payload_and_persists_payment(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        Setting::set('pix_expiration_minutes', 20, 'integer');
        $order = $this->createOrder([
            'total_adults' => 1,
            'total_children' => 1,
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);
        $this->addPassenger($order, ['phone' => '31988887777']);

        Http::fake([
            'https://abacate.test/v1/pixQrCode/create' => Http::response([
                'data' => [
                    'id' => 'pix-123',
                    'brCode' => '000201PIX',
                    'brCodeBase64' => 'data:image/png;base64,BASE64PIX',
                    'devMode' => true,
                    'expiresAt' => '2026-05-14T10:20:00Z',
                ],
            ]),
        ]);

        $payment = (new AbacatePayService)->createCheckout($order, 'pix', [
            'payer' => [
                'name' => 'Pagador Teste',
                'email' => 'pagador@example.com',
                'document' => '12345678900',
            ],
        ]);

        $this->assertSame('abacatepay', $payment->gateway);
        $this->assertSame('pix-123', $payment->external_checkout_id);
        $this->assertSame('000201PIX', $payment->payment_url);
        $this->assertSame('pix', $payment->payment_method);
        $this->assertEqualsWithDelta(260, (float) $payment->amount, 0.01);
        $this->assertTrue($payment->expires_at->equalTo(Carbon::parse('2026-05-14 10:20:00')));
        $this->assertSame('BASE64PIX', $payment->gateway_response['pix_qrcode']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://abacate.test/v1/pixQrCode/create'
                && $request->hasHeader('Authorization', 'Bearer abacate-key')
                && $request['amount'] === 26000
                && $request['customer']['name'] === 'Pagador Teste'
                && $request['customer']['cellphone'] === '31988887777'
                && $request['customer']['taxId'] === '12345678900'
                && $request['metadata']['externalId'] !== '';
        });
    }

    public function test_create_checkout_throws_when_pix_id_is_missing(): void
    {
        $order = $this->createOrder();
        $this->addFlight($order);
        $this->addPassenger($order);

        Http::fake([
            'https://abacate.test/v1/pixQrCode/create' => Http::response(['data' => []]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AbacatePay não retornou ID do PIX.');

        (new AbacatePayService)->createCheckout($order);
    }

    public function test_get_checkout_status_maps_gateway_statuses_and_falls_back_without_pix_id(): void
    {
        $order = $this->createOrder();
        $payment = $this->addPayment($order, [
            'gateway' => 'abacatepay',
            'external_checkout_id' => 'pix-123',
            'status' => 'pending',
        ]);
        $paidWithoutExternalId = $this->addPayment($order, [
            'gateway' => 'abacatepay',
            'external_checkout_id' => null,
            'status' => 'paid',
        ]);

        Http::fake([
            'https://abacate.test/v1/pixQrCode/check*' => Http::response([
                'data' => ['status' => 'PAID'],
            ]),
        ]);

        $service = new AbacatePayService;

        $this->assertSame('paid', $service->getCheckoutStatus($payment));
        $this->assertSame('paid', $service->getCheckoutStatus($paidWithoutExternalId));
    }

    public function test_get_checkout_status_returns_pending_on_failed_response(): void
    {
        $payment = $this->addPayment($this->createOrder(), [
            'gateway' => 'abacatepay',
            'external_checkout_id' => 'pix-123',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://abacate.test/v1/pixQrCode/check*' => Http::response(['error' => true], 500),
        ]);

        $this->assertSame('pending', (new AbacatePayService)->getCheckoutStatus($payment));
    }

    public function test_cancel_checkout_marks_payment_as_cancelled(): void
    {
        $payment = $this->addPayment($this->createOrder(), [
            'gateway' => 'abacatepay',
            'status' => 'pending',
        ]);

        (new AbacatePayService)->cancelCheckout($payment);

        $this->assertSame('cancelled', $payment->fresh()->status);
    }

    public function test_refund_payment_posts_withdraw_request_with_clean_cpf(): void
    {
        $order = $this->createOrder();
        $this->addPassenger($order, ['document' => '123.456.789-00']);
        $payment = $this->addPayment($order, [
            'gateway' => 'abacatepay',
            'amount' => 12.34,
        ]);

        Http::fake([
            'https://abacate.test/v1/withdraw/create' => Http::response([
                'data' => ['id' => 'withdraw-123'],
            ]),
        ]);

        $this->assertTrue((new AbacatePayService)->refundPayment($payment));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://abacate.test/v1/withdraw/create'
                && $request['externalId'] === 'refund-1'
                && $request['method'] === 'PIX'
                && $request['amount'] === 1234
                && $request['pix']['type'] === 'CPF'
                && $request['pix']['key'] === '12345678900';
        });
    }

    public function test_refund_payment_rejects_missing_cpf_or_amount_below_minimum(): void
    {
        $orderWithoutPassenger = $this->createOrder();
        $missingCpfPayment = $this->addPayment($orderWithoutPassenger, [
            'gateway' => 'abacatepay',
            'amount' => 10,
        ]);

        $order = $this->createOrder();
        $this->addPassenger($order);
        $smallPayment = $this->addPayment($order, [
            'gateway' => 'abacatepay',
            'amount' => 3.49,
        ]);

        Http::fake();

        $service = new AbacatePayService;

        $this->assertFalse($service->refundPayment($missingCpfPayment));
        $this->assertFalse($service->refundPayment($smallPayment));
        Http::assertNothingSent();
    }

    public function test_simulate_payment_returns_data_or_throws_on_failure(): void
    {
        Http::fake([
            'https://abacate.test/v1/pixQrCode/simulate-payment?id=pix-ok' => Http::response([
                'data' => ['status' => 'PAID'],
            ]),
            'https://abacate.test/v1/pixQrCode/simulate-payment?id=pix-fail' => Http::response([
                'error' => true,
            ], 500),
        ]);

        $service = new AbacatePayService;

        $this->assertSame(['status' => 'PAID'], $service->simulatePayment('pix-ok'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha ao simular pagamento AbacatePay: HTTP 500');

        $service->simulatePayment('pix-fail');
    }
}
