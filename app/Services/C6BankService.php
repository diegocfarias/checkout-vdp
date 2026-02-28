<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class C6BankService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.c6bank.base_url', ''), '/');
        $this->clientId = config('services.c6bank.client_id', '');
        $this->clientSecret = config('services.c6bank.client_secret', '');
        $this->apiKey = config('services.c6bank.api_key', '');
    }

    /**
     * Cria um checkout na C6Bank e retorna o OrderPayment criado.
     */
    public function createCheckout(Order $order): OrderPayment
    {
        $amount = $this->calculateOrderAmount($order);

        $payload = [
            'amount' => $amount,
            'currency' => 'BRL',
            'reference' => $order->token,
            'description' => "Pedido de passagens #{$order->id}",
            'return_url' => rtrim(config('app.url'), '/') . "/r/{$order->token}/payment-callback",
        ];

        Log::info('C6Bank: criando checkout', [
            'order_id' => $order->id,
            'payload' => $payload,
        ]);

        // TODO: Substituir pelo endpoint real da C6Bank quando a documentação estiver disponível
        $response = Http::withHeaders($this->authHeaders())
            ->timeout(30)
            ->post("{$this->baseUrl}/checkout", $payload);

        if ($response->failed()) {
            Log::error('C6Bank: falha ao criar checkout', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            throw new \RuntimeException("Falha ao criar checkout C6Bank: HTTP {$response->status()}");
        }

        $data = $response->json();

        return OrderPayment::create([
            'order_id' => $order->id,
            'gateway' => 'c6bank',
            'external_checkout_id' => $data['id'] ?? $data['checkout_id'] ?? null,
            'payment_url' => $data['payment_url'] ?? $data['url'] ?? null,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'BRL',
            'gateway_response' => $data,
        ]);
    }

    /**
     * Consulta o status de um checkout na C6Bank.
     * Retorna: 'pending', 'paid', 'failed', 'cancelled', 'expired'
     */
    public function getCheckoutStatus(OrderPayment $payment): string
    {
        $checkoutId = $payment->external_checkout_id;

        if (! $checkoutId) {
            return 'pending';
        }

        Log::info('C6Bank: consultando status', [
            'payment_id' => $payment->id,
            'external_checkout_id' => $checkoutId,
        ]);

        // TODO: Substituir pelo endpoint real da C6Bank
        $response = Http::withHeaders($this->authHeaders())
            ->timeout(15)
            ->get("{$this->baseUrl}/checkout/{$checkoutId}");

        if ($response->failed()) {
            Log::error('C6Bank: falha ao consultar status', [
                'payment_id' => $payment->id,
                'external_checkout_id' => $checkoutId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 'pending';
        }

        $data = $response->json();

        $payment->update(['gateway_response' => $data]);

        // TODO: Ajustar mapeamento de status conforme a documentação real da C6Bank
        $gatewayStatus = $data['status'] ?? 'pending';

        return match ($gatewayStatus) {
            'paid', 'approved', 'confirmed' => 'paid',
            'cancelled', 'canceled' => 'cancelled',
            'expired' => 'expired',
            'failed', 'rejected' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Cancela um checkout pendente na C6Bank.
     */
    public function cancelCheckout(OrderPayment $payment): void
    {
        $checkoutId = $payment->external_checkout_id;

        if (! $checkoutId) {
            $payment->update(['status' => 'cancelled']);
            return;
        }

        Log::info('C6Bank: cancelando checkout', [
            'payment_id' => $payment->id,
            'external_checkout_id' => $checkoutId,
        ]);

        // TODO: Substituir pelo endpoint real da C6Bank
        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->delete("{$this->baseUrl}/checkout/{$checkoutId}");

            if ($response->failed()) {
                Log::warning('C6Bank: falha ao cancelar checkout', [
                    'payment_id' => $payment->id,
                    'external_checkout_id' => $checkoutId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            $payment->update([
                'status' => 'cancelled',
                'gateway_response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('C6Bank: erro ao cancelar checkout', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update(['status' => 'cancelled']);
        }
    }

    private function calculateOrderAmount(Order $order): float
    {
        $total = 0;

        foreach ($order->flights as $flight) {
            $total += (float) ($flight->money_price ?? 0);
            $total += (float) ($flight->tax ?? 0);
        }

        return round($total, 2);
    }

    private function authHeaders(): array
    {
        // TODO: Ajustar headers de autenticação conforme a documentação da C6Bank
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->apiKey,
            'Client-Id' => $this->clientId,
            'Client-Secret' => $this->clientSecret,
        ];
    }
}
