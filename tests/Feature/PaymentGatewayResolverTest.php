<?php

namespace Tests\Feature;

use App\Models\OrderPayment;
use App\Models\Setting;
use App\Services\AbacatePayService;
use App\Services\AppMaxService;
use App\Services\C6BankService;
use App\Services\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PaymentGatewayResolverTest extends TestCase
{
    use RefreshDatabase;

    private AppMaxService $appMaxService;

    private C6BankService $c6BankService;

    private AbacatePayService $abacatePayService;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        $this->appMaxService = Mockery::mock(AppMaxService::class);
        $this->c6BankService = Mockery::mock(C6BankService::class);
        $this->abacatePayService = Mockery::mock(AbacatePayService::class);
    }

    protected function tearDown(): void
    {
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_resolve_uses_global_gateway_config(): void
    {
        config()->set('services.payment.gateway', 'appmax');

        $this->assertSame($this->appMaxService, $this->resolver()->resolve());
    }

    public function test_resolve_for_method_uses_method_settings(): void
    {
        Setting::set('gateway_pix', 'appmax');
        Setting::set('gateway_credit_card', 'abacatepay');
        config()->set('services.payment.gateway', 'c6bank');

        $resolver = $this->resolver();

        $this->assertSame($this->appMaxService, $resolver->resolveForMethod('pix'));
        $this->assertSame($this->abacatePayService, $resolver->resolveForMethod('credit_card'));
        $this->assertSame($this->c6BankService, $resolver->resolveForMethod('boleto'));
    }

    public function test_resolve_for_payment_uses_saved_gateway(): void
    {
        $payment = new OrderPayment(['gateway' => 'abacatepay']);

        $this->assertSame($this->abacatePayService, $this->resolver()->resolveForPayment($payment));
    }

    public function test_invalid_gateway_throws_exception(): void
    {
        config()->set('services.payment.gateway', 'unknown');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway de pagamento inválido: unknown');

        $this->resolver()->resolve();
    }

    private function resolver(): PaymentGatewayResolver
    {
        return new PaymentGatewayResolver(
            $this->appMaxService,
            $this->c6BankService,
            $this->abacatePayService,
        );
    }
}
