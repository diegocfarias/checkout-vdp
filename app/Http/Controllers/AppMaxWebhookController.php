<?php

namespace App\Http\Controllers;

use App\Models\OrderPayment;
use App\Services\BotpressNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppMaxWebhookController extends Controller
{
    /**
     * Processa notificações de pagamento da AppMax.
     * Documentação: https://docs.appmax.com.br/webhooks
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('AppMax webhook recebido', [
            'payload' => $request->all(),
        ]);

        $orderId = $request->input('order_id') ?? $request->input('orderId');
        $status = $request->input('status') ?? $request->input('order_status');

        if (! $orderId) {
            Log::warning('AppMax webhook: order_id não encontrado no payload');

            return response()->json(['received' => true], 200);
        }

        $payment = OrderPayment::where('gateway', 'appmax')
            ->where('external_checkout_id', (string) $orderId)
            ->first();

        if (! $payment) {
            Log::warning('AppMax webhook: OrderPayment não encontrado', ['order_id' => $orderId]);

            return response()->json(['received' => true], 200);
        }

        $order = $payment->order;

        if ($this->isPaidStatus($status)) {
            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => $request->input('payment_method') ?? $payment->payment_method,
                    'external_payment_id' => $request->input('transaction_id') ?? $request->input('payment_id'),
                    'gateway_response' => array_merge($payment->gateway_response ?? [], $request->all()),
                ]);

                $order->update([
                    'status' => 'awaiting_emission',
                    'paid_at' => now(),
                ]);

                if ($order->conversation_id && $order->user_id) {
                    BotpressNotifier::send(
                        $order->conversation_id,
                        $order->user_id,
                        'Pagamento confirmado! Seu pedido está sendo encaminhado para emissão. Em breve você receberá a confirmação.'
                    );
                }

                Log::info('AppMax webhook: pagamento confirmado', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        } elseif ($this->isCancelledStatus($status)) {
            if ($payment->status === 'pending') {
                $payment->update(['status' => 'cancelled']);
                $order->update(['status' => 'cancelled']);

                Log::info('AppMax webhook: pagamento cancelado', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        }

        return response()->json(['received' => true], 200);
    }

    private function isPaidStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        $status = strtolower($status);

        return in_array($status, ['paid', 'approved', 'confirmed', 'integrado', 'completed'], true);
    }

    private function isCancelledStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        $status = strtolower($status);

        return in_array($status, ['cancelled', 'canceled', 'expired', 'failed'], true);
    }
}
