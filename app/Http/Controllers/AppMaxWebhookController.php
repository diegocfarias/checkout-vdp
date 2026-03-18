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
     * Processa notificações de pagamento da AppMax (API v1).
     *
     * Payload esperado:
     *   event: "order_paid" | "order_approved" | "order_authorized" | "order_pix_created" | ...
     *   event_type: "order"
     *   data.order_id: int
     *   data.status: "pendente" | "aprovado" | "autorizado" | "cancelado" | "estornado" | ...
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('AppMax webhook recebido', [
            'payload' => $request->all(),
        ]);

        $event = $request->input('event');
        $eventType = $request->input('event_type');
        $data = $request->input('data', []);

        $orderId = $data['order_id'] ?? $request->input('order_id');
        $status = $data['status'] ?? $request->input('status');

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

        if ($this->isPaidEvent($event) || $this->isPaidStatus($status)) {
            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                    'external_payment_id' => $data['transaction_id'] ?? $data['payment_id'] ?? null,
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
                    'event' => $event,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        } elseif ($this->isCancelledEvent($event) || $this->isCancelledStatus($status)) {
            if ($payment->status === 'pending') {
                $payment->update(['status' => 'cancelled']);
                $order->update(['status' => 'cancelled']);

                Log::info('AppMax webhook: pagamento cancelado/recusado', [
                    'event' => $event,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        } elseif ($this->isRefundEvent($event) || $this->isRefundStatus($status)) {
            $payment->update([
                'status' => 'refunded',
                'gateway_response' => array_merge($payment->gateway_response ?? [], $request->all()),
            ]);
            $order->update(['status' => 'cancelled']);

            Log::info('AppMax webhook: pagamento estornado', [
                'event' => $event,
                'payment_id' => $payment->id,
                'order_id' => $order->id,
            ]);
        }

        return response()->json(['received' => true], 200);
    }

    private function isPaidEvent(?string $event): bool
    {
        if (! $event) {
            return false;
        }

        return in_array($event, [
            'order_paid',
            'order_approved',
            'order_authorized',
            'order_paid_by_pix',
        ], true);
    }

    private function isPaidStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        $status = strtolower($status);

        return in_array($status, ['aprovado', 'autorizado', 'integrado'], true);
    }

    private function isCancelledEvent(?string $event): bool
    {
        if (! $event) {
            return false;
        }

        return in_array($event, [
            'payment_not_authorized',
            'order_pix_expired',
            'order_boleto_expired',
        ], true);
    }

    private function isCancelledStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        $status = strtolower($status);

        return in_array($status, ['cancelado', 'recusado_por_risco'], true);
    }

    private function isRefundEvent(?string $event): bool
    {
        if (! $event) {
            return false;
        }

        return in_array($event, [
            'order_refunded',
            'order_chargeback',
        ], true);
    }

    private function isRefundStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        $status = strtolower($status);

        return in_array($status, ['estornado', 'chargeback_em_tratativa'], true);
    }
}
