<?php

namespace App\Observers;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\BotpressNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    public function created(Order $order): void
    {
        $status = $order->status ?? 'pending';

        $order->statusHistories()->create([
            'status' => $status,
            'description' => OrderStatusHistory::statusLabel($status),
        ]);
    }

    public function updating(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }

        $newStatus = $order->status;

        $order->statusHistories()->create([
            'status' => $newStatus,
            'description' => OrderStatusHistory::statusLabel($newStatus),
        ]);

        $this->notifyWhatsApp($order, $newStatus);
        $this->notifyEmail($order, $newStatus);
    }

    private function notifyWhatsApp(Order $order, string $status): void
    {
        if (! $order->conversation_id || ! $order->user_id) {
            return;
        }

        $message = $this->buildWhatsAppMessage($order, $status);
        if (! $message) {
            return;
        }

        try {
            BotpressNotifier::send($order->conversation_id, $order->user_id, $message);
        } catch (\Throwable $e) {
            Log::warning('OrderObserver: falha ao notificar WhatsApp', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildWhatsAppMessage(Order $order, string $status): ?string
    {
        $trackingUrl = url("/pedido/{$order->tracking_code}");
        $code = $order->tracking_code;

        return match ($status) {
            'awaiting_payment' => "Seu pedido {$code} está aguardando pagamento. Acesse o link para pagar.",
            'awaiting_emission' => "Pagamento confirmado! Acompanhe seu pedido {$code}: {$trackingUrl}",
            'completed' => "Suas passagens foram emitidas! Confira os detalhes do pedido {$code}: {$trackingUrl}",
            'cancelled' => "Seu pedido {$code} foi cancelado.",
            default => null,
        };
    }

    private function notifyEmail(Order $order, string $status): void
    {
        $order->loadMissing(['passengers', 'flights', 'flightSearch']);
        $passenger = $order->passengers->first();

        if (! $passenger || ! $passenger->email) {
            return;
        }

        $payment = $order->latestPayment;

        try {
            Mail::to($passenger->email)->send(new OrderStatusMail($order, $status, $payment));
        } catch (\Throwable $e) {
            Log::warning('OrderObserver: falha ao enviar e-mail', [
                'order_id' => $order->id,
                'email' => $passenger->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
