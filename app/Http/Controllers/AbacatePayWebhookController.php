<?php

namespace App\Http\Controllers;

use App\Models\OrderPayment;
use App\Services\BotpressNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbacatePayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('AbacatePay webhook recebido', [
            'payload' => $request->all(),
        ]);

        $event = $request->input('event');
        $data = $request->input('data', []);

        $pixId = $data['id'] ?? null;

        if (! $pixId) {
            Log::warning('AbacatePay webhook: ID não encontrado no payload');

            return response()->json(['received' => true], 200);
        }

        $payment = OrderPayment::where('gateway', 'abacatepay')
            ->where('external_checkout_id', $pixId)
            ->first();

        if (! $payment) {
            Log::warning('AbacatePay webhook: OrderPayment não encontrado', ['pix_id' => $pixId]);

            return response()->json(['received' => true], 200);
        }

        $order = $payment->order;
        $status = strtoupper($data['status'] ?? '');

        if ($event === 'billing.paid' || $status === 'PAID') {
            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
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

                Log::info('AbacatePay webhook: pagamento confirmado', [
                    'event' => $event,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        } elseif ($event === 'billing.refunded' || $status === 'REFUNDED') {
            $payment->update([
                'status' => 'refunded',
                'gateway_response' => array_merge($payment->gateway_response ?? [], $request->all()),
            ]);
            $order->update(['status' => 'cancelled']);

            Log::info('AbacatePay webhook: pagamento estornado', [
                'event' => $event,
                'payment_id' => $payment->id,
                'order_id' => $order->id,
            ]);
        } elseif (in_array($event, ['billing.failed']) || in_array($status, ['EXPIRED', 'CANCELLED'])) {
            if ($payment->status === 'pending') {
                $payment->update(['status' => 'cancelled']);
                $order->update(['status' => 'cancelled']);

                Log::info('AbacatePay webhook: pagamento cancelado/expirado', [
                    'event' => $event,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        }

        return response()->json(['received' => true], 200);
    }
}
