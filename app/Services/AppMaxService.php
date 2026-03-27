<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppMaxService implements PaymentGatewayInterface
{
    private string $apiUrl;

    private string $authUrl;

    private string $clientId;

    private string $clientSecret;

    private const TOKEN_CACHE_KEY = 'appmax_jwt_token';

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.appmax.api_url', 'https://api.appmax.com.br'), '/');
        $this->authUrl = rtrim(config('services.appmax.auth_url', 'https://auth.appmax.com.br'), '/');
        $this->clientId = (string) config('services.appmax.client_id', '');
        $this->clientSecret = (string) config('services.appmax.client_secret', '');
    }

    public function createCheckout(Order $order, ?string $paymentMethod = null, ?array $cardData = null): OrderPayment
    {
        $order->load(['passengers', 'flights']);
        $paymentMethod = $paymentMethod ?? config('services.appmax.default_payment_method', 'pix');

        $firstPassenger = $order->passengers->first();
        if (! $firstPassenger) {
            throw new \RuntimeException('Pedido sem passageiros para criar checkout AppMax.');
        }

        $orderAmountDecimal = $cardData['total_with_interest'] ?? null;
        if ($orderAmountDecimal === null) {
            $orderAmountDecimal = $this->calculateOrderAmount($order);
        } else {
            $orderAmountDecimal = (float) $orderAmountDecimal;
        }

        $payer = $cardData['payer'] ?? null;
        $customerId = $this->createCustomer($order, $firstPassenger, $cardData['client_ip'] ?? request()->ip(), $payer);
        $appMaxOrderId = $this->createOrder($order, $customerId, $orderAmountDecimal);

        return $this->createPayment($order, $firstPassenger, $appMaxOrderId, $customerId, $paymentMethod, $cardData, $orderAmountDecimal);
    }

    public function getCheckoutStatus(OrderPayment $payment): string
    {
        $externalOrderId = $payment->external_checkout_id;

        if (! $externalOrderId) {
            return $payment->status === 'paid' ? 'paid' : 'pending';
        }

        try {
            $response = $this->authenticatedRequest()
                ->get("{$this->apiUrl}/v1/orders/{$externalOrderId}");

            if ($response->failed()) {
                Log::warning('AppMax: falha ao consultar status do pedido', [
                    'external_order_id' => $externalOrderId,
                    'http_status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $payment->status === 'paid' ? 'paid' : 'pending';
            }

            $json = $response->json();
            $data = $json['data'] ?? $json;
            $orderData = $data['order'] ?? $data;
            $appmaxStatus = strtolower($orderData['status'] ?? '');

            Log::info('AppMax: status do pedido consultado', [
                'external_order_id' => $externalOrderId,
                'appmax_status' => $appmaxStatus,
            ]);

            return match ($appmaxStatus) {
                'aprovado', 'autorizado', 'integrado', 'pago' => 'paid',
                'pendente', 'pendente_integracao' => 'pending',
                'cancelado', 'estornado', 'recusado_por_risco', 'chargeback_em_tratativa' => 'cancelled',
                default => 'pending',
            };
        } catch (\Throwable $e) {
            Log::error('AppMax: erro ao consultar status', ['error' => $e->getMessage()]);

            return $payment->status === 'paid' ? 'paid' : 'pending';
        }
    }

    public function cancelCheckout(OrderPayment $payment): void
    {
        $payment->update(['status' => 'cancelled']);
    }

    public function refundPayment(OrderPayment $payment): bool
    {
        $appMaxOrderId = $payment->external_checkout_id;

        if (! $appMaxOrderId) {
            Log::warning('AppMax: estorno sem order_id externo', ['payment_id' => $payment->id]);

            return false;
        }

        try {
            $response = $this->authenticatedRequest()
                ->post("{$this->apiUrl}/v1/refund", [
                    'order_id' => (int) $appMaxOrderId,
                    'refund_type' => 'total',
                ]);

            if ($response->failed()) {
                Log::error('AppMax: falha ao processar estorno', [
                    'payment_id' => $payment->id,
                    'appmax_order_id' => $appMaxOrderId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('AppMax: estorno processado', [
                'payment_id' => $payment->id,
                'appmax_order_id' => $appMaxOrderId,
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('AppMax: erro ao processar estorno', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Consulta parcelas disponíveis na AppMax para um valor.
     *
     * @return array<int, array{installment: int, value: int, total: int, interest: bool}>
     */
    public function getInstallments(int $amountInCents): array
    {
        try {
            $response = $this->authenticatedRequest()
                ->post("{$this->apiUrl}/v1/payments/installments", [
                    'products_value' => $amountInCents,
                ]);

            if ($response->failed()) {
                Log::warning('AppMax: falha ao consultar parcelas', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return $response->json('data', $response->json());
        } catch (\Throwable $e) {
            Log::error('AppMax: erro ao consultar parcelas', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Solicita estorno total ou parcial na AppMax.
     */
    public function refund(int $appMaxOrderId, string $type = 'total', ?int $valueInCents = null): array
    {
        $payload = [
            'order_id' => $appMaxOrderId,
            'type' => $type,
        ];

        if ($type === 'partial' && $valueInCents !== null) {
            $payload['value'] = $valueInCents;
        }

        $response = $this->authenticatedRequest()
            ->post("{$this->apiUrl}/v1/orders/refund-request", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao solicitar estorno', [
                'order_id' => $appMaxOrderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao solicitar estorno AppMax: HTTP {$response->status()}");
        }

        return $response->json('data', $response->json());
    }

    // ──────────────────────────────────────────────
    // Autenticação OAuth2
    // ──────────────────────────────────────────────

    private function authenticate(): string
    {
        $response = Http::asForm()
            ->timeout(15)
            ->post("{$this->authUrl}/oauth2/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        if ($response->failed()) {
            Log::error('AppMax: falha na autenticação OAuth2', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha na autenticação AppMax: HTTP {$response->status()}");
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;

        if (! $token) {
            throw new \RuntimeException('AppMax: resposta de autenticação não contém access_token.');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($expiresIn - 60));

        return $token;
    }

    private function getToken(): string
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);

        if ($token) {
            return $token;
        }

        return $this->authenticate();
    }

    /**
     * Retorna uma instância Http com o Bearer token já configurado.
     */
    private function authenticatedRequest(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getToken();

        return Http::asJson()
            ->timeout(30)
            ->withToken($token);
    }

    // ──────────────────────────────────────────────
    // Customer
    // ──────────────────────────────────────────────

    private function createCustomer(Order $order, $passenger, ?string $clientIp = null, ?array $payer = null): int
    {
        $payerName = $payer['name'] ?? $passenger->full_name;
        $payerEmail = $payer['email'] ?? $passenger->email;
        $payerDocument = $payer['document'] ?? preg_replace('/\D/', '', $passenger->document ?? '');
        $nameParts = $this->splitFullName($payerName);
        $document = preg_replace('/\D/', '', $payerDocument);
        $phone = preg_replace('/\D/', '', $passenger->phone ?? '');
        $phone = substr($phone, -11);

        $billing = $payer['billing'] ?? null;
        $address = $billing ? [
            'street' => $billing['street'] ?? 'N/A',
            'number' => $billing['number'] ?? '0',
            'complement' => $billing['complement'] ?? '',
            'neighborhood' => $billing['neighborhood'] ?? 'N/A',
            'city' => $billing['city'] ?? 'N/A',
            'state' => $billing['state'] ?? 'SP',
            'zip_code' => preg_replace('/\D/', '', $billing['zipcode'] ?? '00000000'),
            'country' => 'BR',
        ] : [
            'street' => 'N/A',
            'number' => '0',
            'neighborhood' => 'N/A',
            'city' => 'N/A',
            'state' => 'SP',
            'zip_code' => '00000000',
            'country' => 'BR',
        ];

        $payload = [
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'email' => $payerEmail,
            'phone' => $phone ?: '00000000000',
            'document_number' => $document,
            'ip' => $clientIp ?? request()->ip() ?? '127.0.0.1',
            'address' => $address,
        ];

        Log::info('AppMax: criando customer', ['order_id' => $order->id]);

        $response = $this->authenticatedRequest()
            ->post("{$this->apiUrl}/v1/customers", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao criar customer', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao criar customer AppMax: HTTP {$response->status()}");
        }

        $data = $response->json('data', $response->json());
        $customerId = $data['customer']['id'] ?? $data['id'] ?? $data['customer_id'] ?? null;

        if (! $customerId) {
            throw new \RuntimeException('AppMax não retornou customer_id.');
        }

        return (int) $customerId;
    }

    // ──────────────────────────────────────────────
    // Order
    // ──────────────────────────────────────────────

    private function createOrder(Order $order, int $customerId, float $amountDecimal): int
    {
        $amountCents = $this->toCents($amountDecimal);

        $products = [];
        foreach ($order->flights as $flight) {
            $unitValue = $this->toCents((float) ($flight->money_price ?? 0) + (float) ($flight->tax ?? 0));
            $products[] = [
                'sku' => 'FLIGHT-' . ($flight->id ?? $order->id),
                'name' => trim(($flight->cia ?? 'Voo') . ' ' . ($flight->departure_location ?? '') . ' → ' . ($flight->arrival_location ?? '')),
                'quantity' => 1,
                'unit_value' => $unitValue,
                'type' => 'physical',
            ];
        }

        if (empty($products)) {
            $products[] = [
                'sku' => 'ORDER-' . $order->id,
                'name' => 'Passagem aérea #' . $order->id,
                'quantity' => 1,
                'unit_value' => $amountCents,
                'type' => 'physical',
            ];
        }

        $payload = [
            'customer_id' => $customerId,
            'products_value' => $amountCents,
            'discount_value' => 0,
            'shipping_value' => 0,
            'products' => $products,
        ];

        Log::info('AppMax: criando order', ['order_id' => $order->id, 'amount_cents' => $amountCents]);

        $response = $this->authenticatedRequest()
            ->post("{$this->apiUrl}/v1/orders", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao criar order', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao criar order AppMax: HTTP {$response->status()}");
        }

        $data = $response->json('data', $response->json());
        $orderId = $data['order']['id'] ?? $data['id'] ?? $data['order_id'] ?? null;

        if (! $orderId) {
            throw new \RuntimeException('AppMax não retornou order_id.');
        }

        return (int) $orderId;
    }

    // ──────────────────────────────────────────────
    // Payment
    // ──────────────────────────────────────────────

    private function createPayment(
        Order $order,
        $firstPassenger,
        int $appMaxOrderId,
        int $customerId,
        string $paymentMethod,
        ?array $cardData,
        float $amountDecimal
    ): OrderPayment {
        $payer = $cardData['payer'] ?? null;
        $document = $payer ? preg_replace('/\D/', '', $payer['document'] ?? '') : preg_replace('/\D/', '', $firstPassenger->document ?? '');

        $paymentResponse = match ($paymentMethod) {
            'credit_card', 'credit-card' => $this->payWithCreditCard($appMaxOrderId, $customerId, $document, $firstPassenger, $cardData ?? []),
            'boleto' => $this->payWithBoleto($appMaxOrderId, $document),
            default => $this->payWithPix($appMaxOrderId, $document),
        };

        $paymentUrl = $this->extractPaymentUrl($paymentResponse, $paymentMethod);

        return OrderPayment::create([
            'order_id' => $order->id,
            'gateway' => 'appmax',
            'external_checkout_id' => (string) $appMaxOrderId,
            'payment_url' => $paymentUrl,
            'status' => 'pending',
            'amount' => $amountDecimal,
            'currency' => 'BRL',
            'payment_method' => $paymentMethod,
            'gateway_response' => $paymentResponse,
        ]);
    }

    private function payWithCreditCard(int $orderId, int $customerId, string $document, $passenger, array $cardData): array
    {
        if (empty($cardData)) {
            throw new \RuntimeException('Dados do cartão são obrigatórios para pagamento com cartão de crédito.');
        }

        $cardToken = $cardData['card_token'] ?? $cardData['token'] ?? null;
        $cardNumber = preg_replace('/\D/', '', $cardData['card_number'] ?? $cardData['number'] ?? '');

        if (empty($cardToken) && ! empty($cardNumber)) {
            $cardToken = $this->tokenizeCard($cardData, $passenger->full_name);
        }

        $creditCardPayload = [
            'holder_name' => $cardData['card_name'] ?? $cardData['name'] ?? $passenger->full_name,
            'holder_document_number' => $document,
            'cvv' => $cardData['card_cvv'] ?? $cardData['cvv'] ?? '',
            'installments' => (int) ($cardData['installments'] ?? 1),
            'soft_descriptor' => config('services.appmax.soft_descriptor', 'VDP'),
        ];

        if ($cardToken) {
            $creditCardPayload['token'] = $cardToken;
        } else {
            $creditCardPayload['number'] = $cardNumber;
            $creditCardPayload['expiration_month'] = str_pad($cardData['card_month'] ?? $cardData['month'] ?? '01', 2, '0', STR_PAD_LEFT);
            $creditCardPayload['expiration_year'] = str_pad($cardData['card_year'] ?? $cardData['year'] ?? date('y'), 2, '0', STR_PAD_LEFT);
        }

        if (empty($creditCardPayload['token']) && empty($creditCardPayload['number'])) {
            throw new \RuntimeException('Dados do cartão são obrigatórios para pagamento com cartão de crédito.');
        }

        $payload = [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'payment_data' => [
                'credit_card' => $creditCardPayload,
            ],
        ];

        Log::info('AppMax: processando pagamento cartão de crédito', ['appmax_order_id' => $orderId]);

        $response = $this->authenticatedRequest()
            ->post("{$this->apiUrl}/v1/payments/credit-card", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao processar pagamento cartão', [
                'appmax_order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao processar pagamento AppMax (cartão): HTTP {$response->status()}");
        }

        return $response->json('data', $response->json());
    }

    private function payWithPix(int $orderId, string $document): array
    {
        $payload = [
            'order_id' => $orderId,
            'payment_data' => [
                'pix' => [
                    'document_number' => $document,
                ],
            ],
        ];

        Log::info('AppMax: processando pagamento PIX', ['appmax_order_id' => $orderId]);

        $response = $this->authenticatedRequest()
            ->post("{$this->apiUrl}/v1/payments/pix", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao processar pagamento PIX', [
                'appmax_order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao processar pagamento AppMax (PIX): HTTP {$response->status()}");
        }

        return $response->json('data', $response->json());
    }

    private function payWithBoleto(int $orderId, string $document): array
    {
        $payload = [
            'order_id' => $orderId,
            'payment_data' => [
                'boleto' => [
                    'document_number' => $document,
                ],
            ],
        ];

        Log::info('AppMax: processando pagamento boleto', ['appmax_order_id' => $orderId]);

        $response = $this->authenticatedRequest()
            ->post("{$this->apiUrl}/v1/payments/boleto", $payload);

        if ($response->failed()) {
            Log::error('AppMax: falha ao processar pagamento boleto', [
                'appmax_order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Falha ao processar pagamento AppMax (boleto): HTTP {$response->status()}");
        }

        return $response->json('data', $response->json());
    }

    // ──────────────────────────────────────────────
    // Tokenização
    // ──────────────────────────────────────────────

    /**
     * Tokeniza cartão server-side via API AppMax.
     * Preferencialmente usar o appmax.min.js no frontend para PCI DSS compliance.
     */
    public function tokenizeCard(array $cardData, ?string $holderName = null): ?string
    {
        $cardNumber = preg_replace('/\D/', '', $cardData['card_number'] ?? $cardData['number'] ?? '');
        if (strlen($cardNumber) < 13) {
            return null;
        }

        $payload = [
            'payment_data' => [
                'credit_card' => [
                    'number' => $cardNumber,
                    'cvv' => $cardData['card_cvv'] ?? $cardData['cvv'] ?? '',
                    'expiration_month' => str_pad($cardData['card_month'] ?? $cardData['month'] ?? '01', 2, '0', STR_PAD_LEFT),
                    'expiration_year' => str_pad($cardData['card_year'] ?? $cardData['year'] ?? date('y'), 2, '0', STR_PAD_LEFT),
                    'holder_name' => $holderName ?? $cardData['card_name'] ?? $cardData['name'] ?? 'N/A',
                ],
            ],
        ];

        try {
            $response = $this->authenticatedRequest()
                ->timeout(15)
                ->post("{$this->apiUrl}/v1/payments/tokenize", $payload);

            if ($response->failed()) {
                Log::warning('AppMax: tokenização falhou', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json('data', $response->json());

            return $data['token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('AppMax: exceção na tokenização', ['error' => $e->getMessage()]);

            return null;
        }
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function extractPaymentUrl(array $data, string $paymentMethod): ?string
    {
        if (in_array($paymentMethod, ['credit_card', 'credit-card'])) {
            return $data['payment_url'] ?? null;
        }

        if ($paymentMethod === 'pix') {
            $pix = $data['payment'] ?? $data['pix'] ?? $data;

            return $pix['pix_emv'] ?? $pix['copy_paste'] ?? $pix['qr_code'] ?? $pix['pix_copy_paste'] ?? $data['payment_url'] ?? null;
        }

        if ($paymentMethod === 'boleto') {
            return $data['boleto_url'] ?? $data['payment_url'] ?? null;
        }

        return $data['payment_url'] ?? null;
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

    private function toCents(float $value): int
    {
        return (int) round($value * 100);
    }

    private function splitFullName(string $fullName): array
    {
        $parts = array_values(array_filter(explode(' ', trim($fullName))));

        if (count($parts) === 0) {
            return ['first_name' => 'Cliente', 'last_name' => 'N/A'];
        }

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'last_name' => 'N/A'];
        }

        $firstName = array_shift($parts);

        return [
            'first_name' => $firstName,
            'last_name' => implode(' ', $parts),
        ];
    }
}
