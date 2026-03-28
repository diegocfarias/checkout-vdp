<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\OrderPayment;
use App\Models\Setting;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    public function __construct(
        private AppMaxService $appMaxService,
        private C6BankService $c6BankService,
        private AbacatePayService $abacatePayService,
    ) {}

    /**
     * Retorna o gateway configurado globalmente (fallback para compatibilidade).
     */
    public function resolve(): PaymentGatewayInterface
    {
        $gateway = config('services.payment.gateway', 'c6bank');

        return $this->resolveByName($gateway);
    }

    /**
     * Retorna o gateway configurado para um método de pagamento específico.
     */
    public function resolveForMethod(string $paymentMethod): PaymentGatewayInterface
    {
        $settingKey = match ($paymentMethod) {
            'pix' => 'gateway_pix',
            'credit_card' => 'gateway_credit_card',
            default => null,
        };

        $gateway = $settingKey ? Setting::get($settingKey) : null;

        if (empty($gateway)) {
            $gateway = config('services.payment.gateway', 'c6bank');
        }

        return $this->resolveByName($gateway);
    }

    /**
     * Retorna o gateway apropriado para um pagamento existente (baseado no gateway salvo).
     */
    public function resolveForPayment(OrderPayment $payment): PaymentGatewayInterface
    {
        return $this->resolveByName($payment->gateway);
    }

    private function resolveByName(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'appmax' => $this->appMaxService,
            'c6bank' => $this->c6BankService,
            'abacatepay' => $this->abacatePayService,
            default => throw new InvalidArgumentException("Gateway de pagamento inválido: {$gateway}"),
        };
    }
}
