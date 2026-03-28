<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\BotpressNotifier;
use App\Services\PaymentGatewayResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCheckouts extends Command
{
    protected $signature = 'orders:expire-checkouts';

    protected $description = 'Cancela checkouts de pedidos expirados e pagamentos PIX expirados';

    public function handle(PaymentGatewayResolver $paymentResolver): int
    {
        $this->expirePixPayments($paymentResolver);
        $this->expireOrders($paymentResolver);

        return self::SUCCESS;
    }

    private function expirePixPayments(PaymentGatewayResolver $paymentResolver): void
    {
        $payments = OrderPayment::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('order')
            ->get();

        if ($payments->isEmpty()) {
            return;
        }

        $this->info("Expirando {$payments->count()} pagamento(s) PIX...");

        foreach ($payments as $payment) {
            try {
                $payment->update(['status' => 'expired']);

                $order = $payment->order;
                if ($order && in_array($order->status, ['pending', 'awaiting_payment'])) {
                    try {
                        $paymentResolver->resolveForPayment($payment)->cancelCheckout($payment);
                    } catch (\Throwable $e) {
                        Log::warning('ExpireCheckouts: falha ao cancelar no gateway', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $order->update(['status' => 'cancelled']);

                    if ($order->conversation_id && $order->user_id) {
                        BotpressNotifier::send(
                            $order->conversation_id,
                            $order->user_id,
                            'Seu código PIX expirou e o pedido foi cancelado. Se ainda deseja prosseguir, solicite um novo link.'
                        );
                    }

                    $this->info("PIX #{$payment->id} expirado, pedido #{$order->id} cancelado.");
                }
            } catch (\Throwable $e) {
                Log::error('ExpireCheckouts: erro ao expirar pagamento PIX', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Erro ao expirar PIX #{$payment->id}: {$e->getMessage()}");
            }
        }
    }

    private function expireOrders(PaymentGatewayResolver $paymentResolver): void
    {
        $orders = Order::awaitingPayment()
            ->where('expires_at', '<', now())
            ->with('latestPayment')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nenhum checkout expirado encontrado.');

            return;
        }

        $this->info("Processando {$orders->count()} pedido(s) expirado(s)...");

        foreach ($orders as $order) {
            try {
                $payment = $order->latestPayment;

                if ($payment && $payment->status === 'pending') {
                    $payment->update(['status' => 'expired']);

                    try {
                        $paymentResolver->resolveForPayment($payment)->cancelCheckout($payment);
                    } catch (\Throwable $e) {
                        Log::warning('ExpireCheckouts: falha ao cancelar pagamento no gateway', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $order->update(['status' => 'cancelled']);

                if ($order->conversation_id && $order->user_id) {
                    BotpressNotifier::send(
                        $order->conversation_id,
                        $order->user_id,
                        'Seu link de pagamento expirou e o pedido foi cancelado. Se ainda deseja prosseguir, solicite um novo link.'
                    );
                }

                $this->info("Pedido #{$order->id} cancelado com sucesso.");
            } catch (\Throwable $e) {
                Log::error('ExpireCheckouts: erro ao processar pedido', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Erro ao processar pedido #{$order->id}: {$e->getMessage()}");
            }
        }
    }
}
