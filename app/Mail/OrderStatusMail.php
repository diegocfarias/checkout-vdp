<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use SerializesModels;

    public Order $order;

    public string $newStatus;

    public string $statusLabel;

    public string $trackingUrl;

    public ?OrderPayment $payment;

    public function __construct(Order $order, string $newStatus, ?OrderPayment $payment = null)
    {
        $this->order = $order;
        $this->newStatus = $newStatus;
        $this->statusLabel = OrderStatusHistory::statusLabel($newStatus);
        $this->trackingUrl = route('tracking.show', ['trackingCode' => $order->tracking_code]) . '?token=' . $order->token;
        $this->payment = $payment;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->newStatus) {
            'awaiting_payment' => "Pedido {$this->order->tracking_code} - Aguardando pagamento",
            'awaiting_emission' => "Pedido {$this->order->tracking_code} - Pagamento confirmado!",
            'completed' => "Pedido {$this->order->tracking_code} - Passagens emitidas!",
            'cancelled' => "Pedido {$this->order->tracking_code} - Cancelado",
            default => "Pedido {$this->order->tracking_code} - Atualização de status",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-status');
    }
}
