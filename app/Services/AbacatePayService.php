<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbacatePayService implements PaymentGatewayInterface
{
    private string $apiUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.abacatepay.api_url', 'https://api.abacatepay.com/v1'), '/');
        $this->apiKey = (string) config('services.abacatepay.api_key', '');
    }

    public function createCheckout(Order $order, ?string $paymentMethod = null, ?array $cardData = null): OrderPayment
    {
        $order->load('flights');

        $amountCents = $this->calculateAmountCents($order, $cardData);

        $payload = [
            'amount' => $amountCents,
            'description' => "Pedido #{$order->id} - Voe de Primeira",
        ];

        $firstPassenger = $order->passengers->first();
        if ($firstPassenger) {
            $payload['customer'] = [
                'name' => trim($firstPassenger->first_name . ' ' . $firstPassenger->last_name),
                'cellphone' => $firstPassenger->phone ?? '',
                'email' => $firstPassenger->email ?? '',
                'taxId' => $firstPassenger->document ?? '',
            ];
        }

        $payload['metadata'] = [
            'externalId' => (string) $order->id,
        ];

        Log::info('AbacatePay: criando QR Code PIX', [
            'order_id' => $order->id,
            'amount_cents' => $amountCents,
        ]);

        $response = $this->request()
            ->post("{$this->apiUrl}/pixQrCode/create", $payload);

        if ($response->failed()) {
            Log::error('AbacatePay: falha ao criar PIX', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao criar PIX AbacatePay: HTTP {$response->status()}");
        }

        $data = $response->json('data', $response->json());

        $pixId = $data['id'] ?? null;
        if (! $pixId) {
            throw new \RuntimeException('AbacatePay não retornou ID do PIX.');
        }

        $brCode = $data['brCode'] ?? '';
        $brCodeBase64 = $data['brCodeBase64'] ?? null;

        return $order->payments()->create([
            'gateway' => 'abacatepay',
            'external_checkout_id' => $pixId,
            'payment_url' => $brCode,
            'status' => 'pending',
            'payment_method' => 'pix',
            'amount' => $amountCents / 100,
            'currency' => 'BRL',
            'gateway_response' => [
                'pix_emv' => $brCode,
                'pix_qrcode' => $brCodeBase64 ? $this->extractBase64Data($brCodeBase64) : null,
                'pix_id' => $pixId,
                'dev_mode' => $data['devMode'] ?? false,
                'expires_at' => $data['expiresAt'] ?? null,
            ],
        ]);
    }

    public function getCheckoutStatus(OrderPayment $payment): string
    {
        $pixId = $payment->external_checkout_id;

        if (! $pixId) {
            return $payment->status === 'paid' ? 'paid' : 'pending';
        }

        try {
            $response = $this->request()
                ->get("{$this->apiUrl}/pixQrCode/check", ['id' => $pixId]);

            if ($response->failed()) {
                Log::warning('AbacatePay: falha ao consultar status', [
                    'pix_id' => $pixId,
                    'http_status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $payment->status === 'paid' ? 'paid' : 'pending';
            }

            $data = $response->json('data', $response->json());
            $status = strtoupper($data['status'] ?? '');

            Log::info('AbacatePay: status consultado', [
                'pix_id' => $pixId,
                'abacatepay_status' => $status,
            ]);

            return match ($status) {
                'PAID' => 'paid',
                'PENDING' => 'pending',
                'EXPIRED' => 'expired',
                'CANCELLED' => 'cancelled',
                'REFUNDED' => 'cancelled',
                default => 'pending',
            };
        } catch (\Throwable $e) {
            Log::error('AbacatePay: erro ao consultar status', ['error' => $e->getMessage()]);

            return $payment->status === 'paid' ? 'paid' : 'pending';
        }
    }

    public function cancelCheckout(OrderPayment $payment): void
    {
        $payment->update(['status' => 'cancelled']);
    }

    public function refundPayment(OrderPayment $payment): bool
    {
        $order = $payment->order;
        $order->loadMissing('passengers');

        $passenger = $order->passengers->first();
        $cpf = $passenger?->document ?? null;

        if (! $cpf) {
            Log::warning('AbacatePay: estorno sem CPF do passageiro', ['payment_id' => $payment->id]);

            return false;
        }

        $amountCents = (int) round(($payment->amount ?? 0) * 100);
        if ($amountCents < 350) {
            Log::warning('AbacatePay: valor abaixo do mínimo para saque (R$3,50)', [
                'payment_id' => $payment->id,
                'amount_cents' => $amountCents,
            ]);

            return false;
        }

        $cpfClean = preg_replace('/\D/', '', $cpf);

        try {
            $response = $this->request()
                ->post("{$this->apiUrl}/withdraw/create", [
                    'externalId' => "refund-{$payment->id}",
                    'method' => 'PIX',
                    'amount' => $amountCents,
                    'description' => "Estorno pedido #{$order->id} - {$order->tracking_code}",
                    'pix' => [
                        'type' => 'CPF',
                        'key' => $cpfClean,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('AbacatePay: falha ao processar estorno', [
                    'payment_id' => $payment->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json('data', $response->json());

            Log::info('AbacatePay: estorno processado', [
                'payment_id' => $payment->id,
                'withdraw_id' => $data['id'] ?? null,
                'amount_cents' => $amountCents,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('AbacatePay: erro ao processar estorno', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Simula pagamento em modo dev (útil para testes).
     */
    public function simulatePayment(string $pixId): array
    {
        $response = $this->request()
            ->post("{$this->apiUrl}/pixQrCode/simulate-payment?id={$pixId}", [
                'metadata' => new \stdClass,
            ]);

        if ($response->failed()) {
            Log::error('AbacatePay: falha ao simular pagamento', [
                'pix_id' => $pixId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao simular pagamento AbacatePay: HTTP {$response->status()}");
        }

        return $response->json('data', $response->json());
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    private function calculateAmountCents(Order $order, ?array $cardData): int
    {
        if (isset($cardData['total_with_interest'])) {
            return (int) round($cardData['total_with_interest'] * 100);
        }

        $total = 0;
        foreach ($order->flights as $flight) {
            $total += (float) ($flight->money_price ?? 0);
            $total += (float) ($flight->tax ?? 0);
        }

        return (int) round($total * 100);
    }

    private function extractBase64Data(string $dataUri): string
    {
        if (str_starts_with($dataUri, 'data:')) {
            $parts = explode(',', $dataUri, 2);

            return $parts[1] ?? $dataUri;
        }

        return $dataUri;
    }
}
