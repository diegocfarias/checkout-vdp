<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppMaxService implements PaymentGatewayInterface
{
    private string $baseUrl;

    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.appmax.base_url', ''), '/');
        $this->accessToken = config('services.appmax.access_token', '');
    }

    /**
     * Cria um checkout na AppMax (Customer → Order → Payment) e retorna o OrderPayment criado.
     *
     * @param  array<string, mixed>|null  $cardData  Dados do cartão quando paymentMethod = credit_card
     */
    public function createCheckout(Order $order, ?string $paymentMethod = null, ?array $cardData = null): OrderPayment
    {
        $order->load(['passengers', 'flights']);
        $paymentMethod = $paymentMethod ?? config('services.appmax.default_payment_method', 'pix');

        $firstPassenger = $order->passengers->first();
        if (! $firstPassenger) {
            throw new \RuntimeException('Pedido sem passageiros para criar checkout AppMax.');
        }

        $customerId = $this->createCustomer($order, $firstPassenger);
        $appMaxOrderId = $this->createOrder($order, $customerId);

        return $this->createPayment($order, $firstPassenger, $appMaxOrderId, $customerId, $paymentMethod, $cardData);
    }

    /**
     * AppMax não possui endpoint de consulta de status. Retorna 'pending' até o webhook atualizar.
     */
    public function getCheckoutStatus(OrderPayment $payment): string
    {
        return $payment->status === 'paid' ? 'paid' : 'pending';
    }

    /**
     * AppMax não possui cancelamento de transação pendente. Apenas atualiza status local.
     */
    public function cancelCheckout(OrderPayment $payment): void
    {
        $payment->update(['status' => 'cancelled']);
    }

    private function createCustomer(Order $order, $firstPassenger): int
    {
        $nameParts = $this->splitFullName($firstPassenger->full_name);
        $document = preg_replace('/\D/', '', $firstPassenger->document ?? '');
        $phone = preg_replace('/\D/', '', $firstPassenger->phone ?? '');
        $phone = substr($phone, -11);

        $payload = [
            'access-token' => $this->accessToken,
            'firstname' => $nameParts['firstname'],
            'lastname' => $nameParts['lastname'],
            'email' => $firstPassenger->email,
            'telephone' => $phone ?: '00000000000',
            'ip' => request()->ip() ?? '127.0.0.1',
            'postcode' => '00000000',
            'address_street' => 'N/A',
            'address_street_number' => '0',
            'address_street_district' => 'N/A',
            'address_city' => 'N/A',
            'address_state' => 'SP',
        ];

        Log::info('AppMax: criando customer', ['order_id' => $order->id]);

        $response = Http::asJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/api/v3/customer", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao criar customer', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao criar customer AppMax: HTTP {$response->status()}");
        }

        $data = $response->json();
        $customerId = $data['customer_id'] ?? $data['id'] ?? null;

        if (! $customerId) {
            throw new \RuntimeException('AppMax não retornou customer_id.');
        }

        return (int) $customerId;
    }

    private function createOrder(Order $order, int $customerId): int
    {
        $amount = $this->calculateOrderAmount($order);

        $payload = [
            'access-token' => $this->accessToken,
            'total' => $amount,
            'products' => [
                [
                    'sku' => 'PASSAGEM-' . $order->id,
                    'name' => 'Passagem aérea #' . $order->id,
                    'qty' => 1,
                ],
            ],
            'customer_id' => $customerId,
            'discount' => 0,
        ];

        Log::info('AppMax: criando order', ['order_id' => $order->id]);

        $response = Http::asJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/api/v3/order", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao criar order', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao criar order AppMax: HTTP {$response->status()}");
        }

        $data = $response->json();
        $orderId = $data['order_id'] ?? $data['id'] ?? null;

        if (! $orderId) {
            throw new \RuntimeException('AppMax não retornou order_id.');
        }

        return (int) $orderId;
    }

    private function createPayment(
        Order $order,
        $firstPassenger,
        int $appMaxOrderId,
        int $customerId,
        string $paymentMethod,
        ?array $cardData
    ): OrderPayment {
        $amount = $this->calculateOrderAmount($order);
        $document = preg_replace('/\D/', '', $firstPassenger->document ?? '');
        $documentFormatted = strlen($document) === 11
            ? substr($document, 0, 3) . '.' . substr($document, 3, 3) . '.' . substr($document, 6, 3) . '-' . substr($document, 9, 2)
            : $document;

        $basePayload = [
            'access-token' => $this->accessToken,
            'cart' => ['order_id' => $appMaxOrderId],
            'customer' => ['customer_id' => $customerId],
        ];

        $endpoint = match ($paymentMethod) {
            'credit_card', 'credit-card' => '/api/v3/payment/credit-card',
            'boleto' => '/api/v3/payment/boleto',
            'pix' => '/api/v3/payment/pix',
            default => '/api/v3/payment/pix',
        };

        if ($paymentMethod === 'credit_card' || $paymentMethod === 'credit-card') {
            if (! $cardData || empty($cardData['number'] ?? null)) {
                throw new \RuntimeException('Dados do cartão são obrigatórios para pagamento com cartão de crédito.');
            }

            $basePayload['payment'] = [
                'CreditCard' => [
                    'number' => preg_replace('/\D/', '', $cardData['number'] ?? ''),
                    'cvv' => $cardData['cvv'] ?? '',
                    'month' => (int) ($cardData['month'] ?? 1),
                    'year' => (int) ($cardData['year'] ?? date('y')),
                    'document_number' => $documentFormatted,
                    'name' => $cardData['name'] ?? $firstPassenger->full_name,
                    'installments' => (int) ($cardData['installments'] ?? 1),
                    'soft_descriptor' => config('app.name', 'VDP') ?: 'VDP',
                ],
            ];
        } elseif ($paymentMethod === 'boleto') {
            $basePayload['payment'] = [
                'Boleto' => [
                    'document_number' => $documentFormatted,
                ],
            ];
        } else {
            $expirationMinutes = config('app.order_expiration_minutes', 30);
            $basePayload['payment'] = [
                'pix' => [
                    'document_number' => $document,
                    'expiration_date' => now()->addMinutes($expirationMinutes)->format('Y-m-d H:i:s'),
                ],
            ];
        }

        Log::info('AppMax: criando payment', [
            'order_id' => $order->id,
            'method' => $paymentMethod,
        ]);

        $response = Http::asJson()
            ->timeout(30)
            ->post("{$this->baseUrl}{$endpoint}", $basePayload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao criar payment', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao criar payment AppMax: HTTP {$response->status()}");
        }

        $data = $response->json();

        $paymentUrl = $data['payment_url'] ?? $data['boleto_url'] ?? $data['pix_copy_paste'] ?? null;
        if (is_array($paymentUrl)) {
            $paymentUrl = $paymentUrl['copy_paste'] ?? $paymentUrl['qr_code'] ?? null;
        }

        return OrderPayment::create([
            'order_id' => $order->id,
            'gateway' => 'appmax',
            'external_checkout_id' => (string) $appMaxOrderId,
            'payment_url' => is_string($paymentUrl) ? $paymentUrl : null,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'BRL',
            'payment_method' => $paymentMethod,
            'gateway_response' => $data,
        ]);
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

    private function splitFullName(string $fullName): array
    {
        $parts = array_filter(explode(' ', trim($fullName)));

        if (count($parts) === 0) {
            return ['firstname' => 'Cliente', 'lastname' => 'N/A'];
        }

        if (count($parts) === 1) {
            return ['firstname' => $parts[0], 'lastname' => 'N/A'];
        }

        $firstname = array_shift($parts);

        return [
            'firstname' => $firstname,
            'lastname' => implode(' ', $parts),
        ];
    }
}
