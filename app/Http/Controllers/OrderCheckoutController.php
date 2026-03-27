<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderPassengersRequest;
use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CustomerService;
use App\Services\PaymentGatewayResolver;
use App\Services\VdpFlightService;
use Illuminate\Support\Facades\Log;

class OrderCheckoutController extends Controller
{
    public function __construct(
        private PaymentGatewayResolver $paymentResolver,
        private VdpFlightService $vdpService,
        private CustomerService $customerService,
    ) {}

    public function show(string $token)
    {
        $order = Order::with(['flights', 'flightSearch'])
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

        $maxInstallments = Setting::get('max_installments', 12);
        $interestRates = Setting::get('interest_rates', []);
        $pixEnabled = Setting::get('pix_enabled', true);
        $creditCardEnabled = Setting::get('credit_card_enabled', true);

        return view('checkout.passengers', [
            'order' => $order,
            'outbound' => $order->flights->firstWhere('direction', 'outbound'),
            'inbound' => $order->flights->firstWhere('direction', 'inbound'),
            'maxInstallments' => $maxInstallments,
            'interestRates' => $interestRates,
            'pixEnabled' => $pixEnabled,
            'creditCardEnabled' => $creditCardEnabled,
        ]);
    }

    public function store(StoreOrderPassengersRequest $request, Order $order)
    {
        if (! $order->isAccessible()) {
            return response()->view('checkout.not-found', [], 404);
        }

        $order->load('flights');
        $priceConfirmed = $request->input('price_confirmed') === '1';

        if (! $priceConfirmed && $order->flight_search_id) {
            $priceChange = $this->checkPriceChange($order);

            if ($priceChange) {
                return view('checkout.price-changed', [
                    'order' => $order,
                    'oldTotal' => $priceChange['old_total'],
                    'newTotal' => $priceChange['new_total'],
                    'diff' => $priceChange['diff'],
                    'formData' => $request->except(['_token', 'price_confirmed']),
                ]);
            }
        }

        $passengers = $request->validated()['passengers'];

        if ($order->passengers()->count() === 0) {
            foreach ($passengers as $passenger) {
                $order->passengers()->create($passenger);
            }
        }

        $paymentMethod = $request->input('payment_method', 'pix');
        $cardData = $paymentMethod === 'credit_card'
            ? $request->only(['card_number', 'card_cvv', 'card_month', 'card_year', 'card_name', 'installments', 'card_token'])
            : null;

        $clientIp = $request->input('client_ip') ?: $request->ip();

        if ($cardData === null) {
            $cardData = ['client_ip' => $clientIp];
        } else {
            $cardData['client_ip'] = $clientIp;
        }

        $payerData = [
            'name' => $request->input('payer_name'),
            'email' => $request->input('payer_email'),
            'document' => preg_replace('/\D/', '', $request->input('payer_document')),
        ];

        if ($paymentMethod === 'credit_card') {
            $payerData['billing'] = [
                'zipcode' => preg_replace('/\D/', '', $request->input('billing_zipcode', '')),
                'street' => $request->input('billing_street'),
                'number' => $request->input('billing_number'),
                'complement' => $request->input('billing_complement'),
                'neighborhood' => $request->input('billing_neighborhood'),
                'city' => $request->input('billing_city'),
                'state' => $request->input('billing_state'),
            ];
        }

        $cardData['payer'] = $payerData;

        if (! $order->customer_id) {
            try {
                $customer = auth('customer')->check()
                    ? auth('customer')->user()
                    : $this->customerService->findOrCreateFromPayer($payerData);

                $order->update(['customer_id' => $customer->id]);
            } catch (\Throwable $e) {
                Log::warning('Checkout: falha ao vincular cliente', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($paymentMethod === 'credit_card' && config('services.payment.gateway') === 'appmax') {
            $installments = (int) ($cardData['installments'] ?? 1);
            if ($installments > 1) {
                $order->load('flights');
                $baseTotal = 0;
                foreach ($order->flights as $flight) {
                    $baseTotal += (float) ($flight->money_price ?? 0);
                    $baseTotal += (float) ($flight->tax ?? 0);
                }
                $allRates = Setting::get('interest_rates', []);
                $rate = $allRates[$installments] ?? 0;
                $cardData['total_with_interest'] = round($baseTotal * (1 + $rate / 100), 2);
            }
        }

        try {
            $gateway = $this->paymentResolver->resolve();
            $payment = $gateway->createCheckout($order->load('flights'), $paymentMethod, $cardData);

            $order->update(['status' => 'awaiting_payment']);

            $isRedirectableUrl = $payment->payment_url
                && filter_var($payment->payment_url, FILTER_VALIDATE_URL)
                && str_starts_with($payment->payment_url, 'http');

            if ($isRedirectableUrl) {
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
            session(["tracking_verified_{$order->tracking_code}" => true]);

            return redirect()->route('tracking.show', $order->tracking_code);
        }

        if ($order->status !== 'awaiting_payment') {
            return response()->view('checkout.not-found', [], 404);
        }

        $payment = $order->latestPayment;

        if (! $payment) {
            return view('checkout.awaiting-payment', ['order' => $order, 'payment' => null]);
        }

        try {
            $status = $this->paymentResolver->resolveForPayment($payment)->getCheckoutStatus($payment);
        } catch (\Throwable $e) {
            Log::error('Checkout: falha ao consultar status', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return view('checkout.awaiting-payment', ['order' => $order, 'payment' => $payment]);
        }

        if ($status === 'paid') {
            $now = now();

            $payment->update([
                'status' => 'paid',
                'paid_at' => $now,
                'payment_method' => $payment->gateway_response['payment_method'] ?? $payment->payment_method,
                'external_payment_id' => $payment->gateway_response['payment_id'] ?? $payment->gateway_response['id'] ?? null,
            ]);

            $order->update([
                'status' => 'awaiting_emission',
                'paid_at' => $now,
            ]);

            session(["tracking_verified_{$order->tracking_code}" => true]);

            return redirect()->route('tracking.show', $order->tracking_code);
        }

        if (in_array($status, ['cancelled', 'expired', 'failed'])) {
            $payment->update(['status' => $status]);
            $order->update(['status' => 'cancelled']);

            return view('checkout.cancelled', ['order' => $order]);
        }

        return view('checkout.awaiting-payment', ['order' => $order, 'payment' => $payment]);
    }

    private function checkPriceChange(Order $order): ?array
    {
        $flightSearch = FlightSearch::find($order->flight_search_id);

        if (! $flightSearch) {
            return null;
        }

        $obFlight = $order->flights->firstWhere('direction', 'outbound');
        $ibFlight = $order->flights->firstWhere('direction', 'inbound');

        if (! $obFlight) {
            return null;
        }

        $oldTotal = 0;
        foreach ($order->flights as $f) {
            $oldTotal += (float) ($f->money_price ?? 0) + (float) ($f->tax ?? 0);
        }

        $baseParams = [
            'departure' => $flightSearch->departure_iata,
            'arrival' => $flightSearch->arrival_iata,
            'outbound_date' => $flightSearch->outbound_date instanceof \Carbon\Carbon
                ? $flightSearch->outbound_date->format('Y-m-d')
                : $flightSearch->outbound_date,
            'inbound_date' => $flightSearch->inbound_date
                ? ($flightSearch->inbound_date instanceof \Carbon\Carbon
                    ? $flightSearch->inbound_date->format('Y-m-d')
                    : $flightSearch->inbound_date)
                : null,
            'adults' => $flightSearch->adults,
            'children' => $flightSearch->children,
            'infants' => $flightSearch->infants,
            'cabin' => $flightSearch->cabin,
        ];

        try {
            $fresh = $this->vdpService->revalidateFlightPair(
                $baseParams,
                $obFlight->unique_id,
                $obFlight->operator ?? 'all',
                $ibFlight?->unique_id,
                $ibFlight?->operator,
            );

            $freshOb = $fresh['outbound'];
            $freshIb = $fresh['inbound'];

            if (! $freshOb) {
                return null;
            }

            $newTotal = $this->vdpService->calculateFlightPrice($freshOb);

            if ($freshIb) {
                $newTotal += $this->vdpService->calculateFlightPrice($freshIb);
            }

            if (abs($newTotal - $oldTotal) >= 0.01) {
                $obFlight->update([
                    'price_money' => $freshOb['price_money'] ?? $obFlight->price_money,
                    'price_miles' => $freshOb['price_miles'] ?? $obFlight->price_miles,
                    'boarding_tax' => $freshOb['boarding_tax'] ?? $obFlight->boarding_tax,
                    'money_price' => $this->vdpService->calculateBasePrice($freshOb),
                    'tax' => $this->vdpService->parseMoneyValue($freshOb['boarding_tax'] ?? '0'),
                ]);

                if ($freshIb && $ibFlight) {
                    $ibFlight->update([
                        'price_money' => $freshIb['price_money'] ?? $ibFlight->price_money,
                        'price_miles' => $freshIb['price_miles'] ?? $ibFlight->price_miles,
                        'boarding_tax' => $freshIb['boarding_tax'] ?? $ibFlight->boarding_tax,
                        'money_price' => $this->vdpService->calculateBasePrice($freshIb),
                        'tax' => $this->vdpService->parseMoneyValue($freshIb['boarding_tax'] ?? '0'),
                    ]);
                }

                return [
                    'old_total' => round($oldTotal, 2),
                    'new_total' => round($newTotal, 2),
                    'diff' => round($newTotal - $oldTotal, 2),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Checkout: falha ao revalidar preço', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return null;
    }

}
