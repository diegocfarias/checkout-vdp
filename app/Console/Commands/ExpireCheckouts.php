<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BotpressNotifier;
use App\Services\C6BankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCheckouts extends Command
{
    protected $signature = 'orders:expire-checkouts';

    protected $description = 'Cancela checkouts de pedidos expirados e notifica o usuário';

    public function handle(C6BankService $c6BankService): int
    {
        $orders = Order::awaitingPayment()
            ->where('expires_at', '<', now())
            ->with('latestPayment')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nenhum checkout expirado encontrado.');
            return self::SUCCESS;
        }

        $this->info("Processando {$orders->count()} pedido(s) expirado(s)...");

        foreach ($orders as $order) {
            try {
                $payment = $order->latestPayment;

                if ($payment && $payment->status === 'pending') {
                    $c6BankService->cancelCheckout($payment);
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

        return self::SUCCESS;
    }
}
