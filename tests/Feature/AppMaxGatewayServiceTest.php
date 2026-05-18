<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AppMaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class AppMaxGatewayServiceTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Setting::clearCache();
        config()->set('services.appmax.api_url', 'https://api.appmax.test');
        config()->set('services.appmax.auth_url', 'https://auth.appmax.test');
        config()->set('services.appmax.client_id', 'client-id');
        config()->set('services.appmax.client_secret', 'client-secret');
        config()->set('services.appmax.soft_descriptor', 'VDPTEST');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_create_checkout_authenticates_and_persists_pix_payment(): void
    {
        Carbon::setTestNow('2026-05-16 09:00:00');
        Setting::set('pix_expiration_minutes', 20, 'integer');
        $order = $this->createOrder([
            'total_adults' => 1,
            'total_children' => 1,
        ]);
        $this->addPassenger($order, [
            'full_name' => 'Ana Pagadora',
            'email' => 'ana@example.com',
            'document' => '529.982.247-25',
            'phone' => '(31) 99999-0000',
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);

        Http::fake($this->successfulCheckoutResponses([
            'https://api.appmax.test/v1/payments/pix' => Http::response([
                'data' => [
                    'payment' => [
                        'pix_emv' => '000201PIX',
                    ],
                ],
            ]),
        ]));

        $payment = (new AppMaxService)->createCheckout($order, 'pix', [
            'client_ip' => '10.0.0.1',
            'payer' => [
                'name' => 'Joao Comprador',
                'email' => 'joao@example.com',
                'document' => '111.444.777-35',
                'billing' => [
                    'street' => 'Rua Teste',
                    'number' => '123',
                    'complement' => 'Apto 1',
                    'neighborhood' => 'Centro',
                    'city' => 'Belo Horizonte',
                    'state' => 'MG',
                    'zipcode' => '30140-071',
                ],
            ],
        ]);

        $this->assertSame('appmax', $payment->gateway);
        $this->assertSame('987', $payment->external_checkout_id);
        $this->assertSame('000201PIX', $payment->payment_url);
        $this->assertSame('pix', $payment->payment_method);
        $this->assertEqualsWithDelta(260, (float) $payment->amount, 0.01);
        $this->assertTrue($payment->expires_at->equalTo(Carbon::parse('2026-05-16 09:20:00')));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://auth.appmax.test/oauth2/token'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'client-id');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.appmax.test/v1/customers'
            && $request['first_name'] === 'Joao'
            && $request['last_name'] === 'Comprador'
            && $request['email'] === 'joao@example.com'
            && $request['document_number'] === '11144477735'
            && $request['ip'] === '10.0.0.1'
            && $request['address']['zip_code'] === '30140071');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.appmax.test/v1/orders'
            && $request['products_value'] === 26000
            && count($request['products']) === 1);
    }

    public function test_create_checkout_processes_credit_card_with_existing_token(): void
    {
        Cache::put('appmax_jwt_token', 'cached-token', now()->addHour());
        $order = $this->createOrder();
        $this->addPassenger($order, [
            'full_name' => 'Maria Cartao',
            'document' => '529.982.247-25',
        ]);
        $this->addFlight($order, [
            'money_price' => '500.00',
            'tax' => '50.00',
        ]);

        Http::fake($this->successfulCheckoutResponses([
            'https://api.appmax.test/v1/payments/credit-card' => Http::response([
                'data' => [
                    'payment_url' => 'https://pay.appmax.test/card',
                    'status' => 'pendente',
                ],
            ]),
        ]));

        $payment = (new AppMaxService)->createCheckout($order, 'credit_card', [
            'total_with_interest' => 333.33,
            'card_token' => 'card-token-123',
            'card_name' => 'MARIA CARTAO',
            'card_cvv' => '123',
            'installments' => 3,
            'payer' => [
                'document' => '529.982.247-25',
            ],
        ]);

        $this->assertSame('https://pay.appmax.test/card', $payment->payment_url);
        $this->assertSame('credit_card', $payment->payment_method);
        $this->assertEqualsWithDelta(333.33, (float) $payment->amount, 0.01);
        $this->assertNull($payment->expires_at);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.appmax.test/v1/payments/credit-card'
            && $request['payment_data']['credit_card']['token'] === 'card-token-123'
            && $request['payment_data']['credit_card']['installments'] === 3
            && $request['payment_data']['credit_card']['soft_descriptor'] === 'VDPTEST');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://auth.appmax.test/oauth2/token');
    }

    public function test_boleto_installments_refund_and_tokenize_helpers(): void
    {
        Cache::put('appmax_jwt_token', 'cached-token', now()->addHour());
        $order = $this->createOrder();
        $this->addPassenger($order, [
            'full_name' => 'Cliente Boleto',
            'document' => '529.982.247-25',
        ]);
        $this->addFlight($order, [
            'money_price' => '100.00',
            'tax' => '30.00',
        ]);

        Http::fake($this->successfulCheckoutResponses([
            'https://api.appmax.test/v1/payments/boleto' => Http::response([
                'data' => [
                    'boleto_url' => 'https://pay.appmax.test/boleto',
                ],
            ]),
            'https://api.appmax.test/v1/payments/installments' => Http::response([
                'data' => [
                    ['installment' => 1, 'value' => 10000, 'total' => 10000, 'interest' => false],
                ],
            ]),
            'https://api.appmax.test/v1/orders/refund-request' => Http::response([
                'data' => ['refund_id' => 123],
            ]),
            'https://api.appmax.test/v1/payments/tokenize' => Http::response([
                'data' => ['token' => 'generated-token'],
            ]),
        ]));

        $service = new AppMaxService;

        $payment = $service->createCheckout($order, 'boleto');
        $installments = $service->getInstallments(10000);
        $refund = $service->refund(987, 'partial', 5000);
        $token = $service->tokenizeCard([
            'card_number' => '4111 1111 1111 1111',
            'card_cvv' => '123',
            'card_month' => '7',
            'card_year' => '30',
        ], 'Cliente Boleto');

        $this->assertSame('https://pay.appmax.test/boleto', $payment->payment_url);
        $this->assertSame('boleto', $payment->payment_method);
        $this->assertSame(1, $installments[0]['installment']);
        $this->assertSame(['refund_id' => 123], $refund);
        $this->assertSame('generated-token', $token);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.appmax.test/v1/orders/refund-request'
            && $request['order_id'] === 987
            && $request['type'] === 'partial'
            && $request['value'] === 5000);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.appmax.test/v1/payments/tokenize'
            && $request['payment_data']['credit_card']['expiration_month'] === '07');
    }

    public function test_appmax_failure_paths_are_reported(): void
    {
        Cache::put('appmax_jwt_token', 'cached-token', now()->addHour());
        $order = $this->createOrder();
        $this->addFlight($order);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pedido sem passageiros');

        (new AppMaxService)->createCheckout($order, 'pix');
    }

    private function successfulCheckoutResponses(array $paymentResponses): array
    {
        return array_merge([
            'https://auth.appmax.test/oauth2/token' => Http::response([
                'access_token' => 'fresh-token',
                'expires_in' => 3600,
            ]),
            'https://api.appmax.test/v1/customers' => Http::response([
                'data' => [
                    'customer' => [
                        'id' => 456,
                    ],
                ],
            ]),
            'https://api.appmax.test/v1/orders' => Http::response([
                'data' => [
                    'order' => [
                        'id' => 987,
                    ],
                ],
            ]),
        ], $paymentResponses);
    }
}
