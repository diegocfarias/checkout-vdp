<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderPassengersRequest;
use App\Models\Order;
use App\Services\BotpressNotifier;
use App\Services\PaymentGatewayResolver;
use Illuminate\Support\Facades\Log;

class OrderCheckoutController extends Controller
{
    public function __construct(
        private PaymentGatewayResolver $paymentResolver,
    ) {}

    public function show(string $token)
    {
        $order = Order::with('flights')
            ->where('token', $token)
            ->pending()
            ->notExpired()
            ->first();

        if (! $order) {
            return response()->view('checkout.not-found', [], 404);
        }

        return view('checkout.resumo', [
            'order' => $order,
            'outbound' => $order->flights->firstWhere('direction', 'outbound'),
            'inbound' => $order->flights->firstWhere('direction', 'inbound'),
        ]);
    }

    public function showPassengers(string $token)
    {
        $order = Order::with('flights')
            ->where('token', $token)
            ->pending()
            ->notExpired()
            ->first();

        if (! $order) {
            return response()->view('checkout.not-found', [], 404);
        }

        return view('checkout.passengers', [
            'order' => $order,
            'outbound' => $order->flights->firstWhere('direction', 'outbound'),
            'inbound' => $order->flights->firstWhere('direction', 'inbound'),
        ]);
    }

    public function store(StoreOrderPassengersRequest $request, Order $order)
    {
        if (! $order->isAccessible()) {
            return response()->view('checkout.not-found', [], 404);
        }

        $passengers = $request->validated()['passengers'];

        foreach ($passengers as $passenger) {
            $order->passengers()->create($passenger);
        }

        $paymentMethod = $request->input('payment_method', 'pix');
        $cardData = $paymentMethod === 'credit_card'
            ? $request->only(['card_number', 'card_cvv', 'card_month', 'card_year', 'card_name', 'installments'])
            : null;

        try {
            $gateway = $this->paymentResolver->resolve();
            $payment = $gateway->createCheckout($order->load('flights'), $paymentMethod, $cardData);

            $order->update(['status' => 'awaiting_payment']);

            if ($payment->payment_url) {
                return redirect()->away($payment->payment_url);
            }

            return view('checkout.awaiting-payment', [
                'order' => $order,
                'payment' => $payment,
            ]);
        } catch (\Throwable $e) {
            Log::error('Checkout: falha ao criar pagamento', [
                'order_id' => $order->id,
                'gateway' => config('services.payment.gateway'),
                'error' => $e->getMessage(),
            ]);

            $order->update(['status' => 'awaiting_payment']);

            return view('checkout.awaiting-payment', ['order' => $order]);
        }
    }

    public function paymentCallback(Order $order)
    {
        if ($order->status === 'cancelled') {
            return view('checkout.cancelled', ['order' => $order]);
        }

        if ($order->status === 'awaiting_emission' || $order->status === 'completed') {
            return view('checkout.success', ['order' => $order->load('flights')]);
        }

        if ($order->status !== 'awaiting_payment') {
            return response()->view('checkout.not-found', [], 404);
        }

        $payment = $order->latestPayment;

        if (! $payment) {
            return view('checkout.awaiting-payment', ['order' => $order]);
        }

        try {
            $status = $this->paymentResolver->resolveForPayment($payment)->getCheckoutStatus($payment);
        } catch (\Throwable $e) {
            Log::error('Checkout: falha ao consultar status', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return view('checkout.awaiting-payment', ['order' => $order]);
        }

        if ($status === 'paid') {
            $now = now();

            $payment->update([
                'status' => 'paid',
                'paid_at' => $now,
                'payment_method' => $payment->gateway_response['payment_method'] ?? null,
                'external_payment_id' => $payment->gateway_response['payment_id'] ?? $payment->gateway_response['id'] ?? null,
            ]);

            $order->update([
                'status' => 'awaiting_emission',
                'paid_at' => $now,
            ]);

            if ($order->conversation_id && $order->user_id) {
                BotpressNotifier::send(
                    $order->conversation_id,
                    $order->user_id,
                    'Pagamento confirmado! Seu pedido está sendo encaminhado para emissão. Em breve você receberá a confirmação.'
                );
            }

            return view('checkout.success', ['order' => $order->load('flights')]);
        }

        if (in_array($status, ['cancelled', 'expired', 'failed'])) {
            $payment->update(['status' => $status]);
            $order->update(['status' => 'cancelled']);

            return view('checkout.cancelled', ['order' => $order]);
        }

        return view('checkout.awaiting-payment', ['order' => $order]);
    }
}
