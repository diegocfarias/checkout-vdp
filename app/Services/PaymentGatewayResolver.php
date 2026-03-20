<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\OrderPayment;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    public function __construct(
        private AppMaxService $appMaxService,
        private C6BankService $c6BankService,
        private AbacatePayService $abacatePayService,
    ) {}

    /**
     * Retorna o gateway configurado (AppMax, C6Bank ou AbacatePay).
     */
    public function resolve(): PaymentGatewayInterface
    {
        $gateway = config('services.payment.gateway', 'c6bank');

        return match ($gateway) {
            'appmax' => $this->appMaxService,
            'c6bank' => $this->c6BankService,
            'abacatepay' => $this->abacatePayService,
            default => throw new InvalidArgumentException("Gateway de pagamento inválido: {$gateway}"),
        };
    }

    /**
     * Retorna o gateway apropriado para um pagamento existente (baseado no gateway usado).
     */
    public function resolveForPayment(OrderPayment $payment): PaymentGatewayInterface
    {
        return match ($payment->gateway) {
            'appmax' => $this->appMaxService,
            'c6bank' => $this->c6BankService,
            'abacatepay' => $this->abacatePayService,
            default => throw new InvalidArgumentException("Gateway de pagamento desconhecido: {$payment->gateway}"),
        };
    }
}
