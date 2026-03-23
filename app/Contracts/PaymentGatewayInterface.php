<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\OrderPayment;

interface PaymentGatewayInterface
{
    /**
     * Cria um checkout e retorna o OrderPayment criado.
     *
     * @param  array<string, mixed>|null  $cardData  Dados do cartão (quando paymentMethod = credit_card)
     */
    public function createCheckout(Order $order, ?string $paymentMethod = null, ?array $cardData = null): OrderPayment;

    /**
     * Consulta o status de um checkout.
     * Retorna: 'pending', 'paid', 'failed', 'cancelled', 'expired'
     */
    public function getCheckoutStatus(OrderPayment $payment): string;

    /**
     * Cancela um checkout pendente.
     */
    public function cancelCheckout(OrderPayment $payment): void;

    /**
     * Estorna um pagamento já confirmado.
     * Retorna true se o estorno foi processado com sucesso.
     */
    public function refundPayment(OrderPayment $payment): bool;
}
