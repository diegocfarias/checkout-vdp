<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderPassengersRequest;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\SavedPassenger;
use App\Models\Setting;
use App\Services\CustomerService;
use App\Services\PaymentGatewayResolver;
use App\Services\ReferralService;
use App\Services\VdpFlightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderCheckoutController extends Controller
{
    public function __construct(
        private PaymentGatewayResolver $paymentResolver,
        private VdpFlightService $vdpService,
        private CustomerService $customerService,
        private ReferralService $referralService,
    ) {}

    public function show(string $token)
    {
        $order = Order::with(['flights', 'flightSearch', 'coupon'])
            ->where('token', $token)
            ->pending()
            ->notExpired()
            ->first();

        if (! $order) {
            return response()->view('checkout.not-found', [], 404);
        }

        $pixDiscount = (float) Setting::get('pix_discount', 0);
        $pixEnabled = ! empty(Setting::get('gateway_pix', config('services.payment.gateway')));

        return view('checkout.resumo', [
            'order' => $order,
            'outbound' => $order->flights->firstWhere('direction', 'outbound'),
            'inbound' => $order->flights->firstWhere('direction', 'inbound'),
            'pixDiscount' => $pixDiscount,
            'pixEnabled' => $pixEnabled,
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

        $gatewayPix = Setting::get('gateway_pix');
        $gatewayCc = Setting::get('gateway_credit_card');

        $pixEnabled = $gatewayPix !== null ? ! empty($gatewayPix) : Setting::get('pix_enabled', true);
        $creditCardEnabled = $gatewayCc !== null ? ! empty($gatewayCc) : Setting::get('credit_card_enabled', true);
        $pixDiscount = (float) Setting::get('pix_discount', 0);

        $ccGateway = $gatewayCc ?: config('services.payment.gateway', 'appmax');
        $maxInstallments = Setting::get('max_installments_' . $ccGateway, Setting::get('max_installments', 12));
        $interestRates = Setting::get('interest_rates_' . $ccGateway, Setting::get('interest_rates', []));

        $savedPassengers = collect();
        $walletBalance = 0;
        $isAffiliate = false;
        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            $savedPassengers = $customer->savedPassengers;
            $walletBalance = $this->referralService->getAvailableBalance($customer);
            $isAffiliate = $customer->isAffiliate();
        }

        $referralEnabled = (bool) Setting::get('referral_enabled', false);
        $refCookie = request()->cookie('ref_code', '');

        return view('checkout.passengers', [
            'order' => $order,
            'outbound' => $order->flights->firstWhere('direction', 'outbound'),
            'inbound' => $order->flights->firstWhere('direction', 'inbound'),
            'maxInstallments' => $maxInstallments,
            'interestRates' => $interestRates,
            'pixEnabled' => $pixEnabled,
            'creditCardEnabled' => $creditCardEnabled,
            'pixDiscount' => $pixDiscount,
            'savedPassengers' => $savedPassengers,
            'walletBalance' => $walletBalance,
            'isAffiliate' => $isAffiliate,
            'referralEnabled' => $referralEnabled,
            'refCookie' => $refCookie,
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
        $rawPassengers = $request->input('passengers', []);

        if ($order->passengers()->count() === 0) {
            foreach ($passengers as $passenger) {
                $order->passengers()->create($passenger);
            }
        }

        if (auth('customer')->check()) {
            /** @var \App\Models\Customer $customer */
            $authCustomer = auth('customer')->user();
            foreach ($rawPassengers as $i => $raw) {
                if (! empty($raw['save_passenger']) && isset($passengers[$i])) {
                    $p = $passengers[$i];
                    $nationality = $p['nationality'] ?? 'BR';
                    $doc = preg_replace('/\D/', '', $p['document'] ?? '');
                    $passport = trim($p['passport_number'] ?? '');

                    if ($nationality === 'BR' && $doc) {
                        $matchKey = ['customer_id' => $authCustomer->id, 'document' => $doc];
                    } elseif ($passport) {
                        $matchKey = ['customer_id' => $authCustomer->id, 'passport_number' => $passport];
                    } else {
                        continue;
                    }

                    SavedPassenger::updateOrCreate($matchKey, [
                        'full_name' => $p['full_name'],
                        'nationality' => $nationality,
                        'document' => $doc ?: null,
                        'passport_number' => $passport ?: null,
                        'passport_expiry' => $p['passport_expiry'] ?? null,
                        'birth_date' => $p['birth_date'],
                        'email' => $p['email'],
                        'phone' => $p['phone'],
                    ]);
                }
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

        $couponCode = strtoupper(trim($request->input('coupon_code', '')));
        $discountAmount = 0;
        $walletAmountUsed = 0;
        $useWallet = $request->boolean('use_wallet');

        if ($useWallet && ! $couponCode) {
            $baseTotal = $this->calculateBaseTotal($order);
            $customer = $order->customer ?? (auth('customer')->check() ? auth('customer')->user() : null);

            if ($customer) {
                $balance = $this->referralService->getAvailableBalance($customer);
                $walletAmountUsed = min($balance, $baseTotal);

                if ($walletAmountUsed > 0) {
                    try {
                        $this->referralService->debitWallet($customer, $order, $walletAmountUsed);
                        $order->update(['wallet_amount_used' => $walletAmountUsed]);
                    } catch (\Throwable $e) {
                        Log::warning('Checkout: falha ao debitar carteira', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                        $walletAmountUsed = 0;
                    }
                }
            }
        } elseif ($couponCode) {
            $resolved = $this->referralService->resolveCode($couponCode);

            if ($resolved && $resolved['type'] === 'referral') {
                $affiliate = $resolved['model'];
                $payerDoc = $request->input('payer_document', '');
                $validation = $this->referralService->validateReferral($affiliate, $payerDoc);

                if ($validation['valid']) {
                    $baseTotal = $this->calculateBaseTotal($order);
                    $referredCustomerId = $order->customer_id;

                    $referral = $this->referralService->applyReferralDiscount(
                        $order, $affiliate, $baseTotal, $payerDoc, $referredCustomerId
                    );

                    $discountAmount = (float) $referral->discount_amount;
                }
            } elseif ($resolved && $resolved['type'] === 'coupon') {
                $coupon = $resolved['model'];

                if ($coupon->isValid()) {
                    $payerDoc = $request->input('payer_document');
                    $customerDoc = auth('customer')->user()?->document;
                    $docValid = $coupon->isAvailableForDocument($payerDoc) || $coupon->isAvailableForDocument($customerDoc);

                    if ($docValid) {
                        $baseTotal = $this->calculateBaseTotal($order);
                        $discountAmount = $coupon->calculateDiscount($baseTotal);

                        $order->update([
                            'coupon_id' => $coupon->id,
                            'discount_amount' => $discountAmount,
                        ]);

                        $coupon->incrementUsage();
                    }
                }
            }
        }

        $baseTotal = $this->calculateBaseTotal($order);
        $totalAfterDiscount = $baseTotal - $discountAmount - $walletAmountUsed;

        if ($paymentMethod === 'pix') {
            $canApplyPixDiscount = true;

            if (isset($referral)) {
                $canApplyPixDiscount = (bool) Setting::get('referral_cumulative_with_pix', true);
            } elseif (isset($coupon)) {
                $canApplyPixDiscount = (bool) $coupon->cumulative_with_pix;
            }

            $pixDiscountPct = (float) Setting::get('pix_discount', 0);
            if ($pixDiscountPct > 0 && $canApplyPixDiscount) {
                $totalAfterDiscount = round($totalAfterDiscount * (1 - $pixDiscountPct / 100), 2);
            }
            $cardData['total_with_interest'] = round($totalAfterDiscount, 2);
        } else {
            $ccGateway = Setting::get('gateway_credit_card') ?: config('services.payment.gateway', 'appmax');
            $installments = (int) ($cardData['installments'] ?? 1);
            $allRates = Setting::get('interest_rates_' . $ccGateway, Setting::get('interest_rates', []));
            $rate = $allRates[$installments] ?? 0;
            $cardData['total_with_interest'] = round($totalAfterDiscount * (1 + $rate / 100), 2);
        }

        if ($totalAfterDiscount <= 0 && $walletAmountUsed > 0) {
            $now = now();
            $order->payments()->create([
                'gateway' => 'wallet',
                'status' => 'paid',
                'payment_method' => 'wallet',
                'amount' => $walletAmountUsed,
                'currency' => 'BRL',
                'paid_at' => $now,
            ]);

            $order->update([
                'status' => 'awaiting_emission',
                'paid_at' => $now,
            ]);

            session(["tracking_verified_{$order->tracking_code}" => true]);

            return redirect()->route('tracking.show', $order->tracking_code);
        }

        try {
            $gateway = $this->paymentResolver->resolveForMethod($paymentMethod);
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

        if ($payment->isExpired() && $payment->status === 'pending') {
            $payment->update(['status' => 'expired']);
            $order->update(['status' => 'cancelled']);

            return view('checkout.awaiting-payment', ['order' => $order, 'payment' => $payment]);
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

    public function applyCoupon(Request $request, string $token)
    {
        $order = Order::with('flights')
            ->where('token', $token)
            ->pending()
            ->notExpired()
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Pedido não encontrado.'], 404);
        }

        $code = strtoupper(trim($request->input('coupon_code', '')));

        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'Informe o código do cupom ou indicação.']);
        }

        $resolved = $this->referralService->resolveCode($code);

        if (! $resolved) {
            return response()->json(['success' => false, 'message' => 'Código inválido ou expirado.']);
        }

        $baseTotal = $this->calculateBaseTotal($order);

        if ($resolved['type'] === 'referral') {
            $affiliate = $resolved['model'];
            $document = $request->input('payer_document', '');
            $validation = $this->referralService->validateReferral($affiliate, $document);

            if (! $validation['valid']) {
                return response()->json(['success' => false, 'message' => $validation['error']]);
            }

            $preview = $this->referralService->previewReferralDiscount($affiliate, $baseTotal);

            return response()->json([
                'success' => true,
                'type' => 'referral',
                'coupon_code' => $code,
                'discount_amount' => $preview['discount_amount'],
                'new_total' => $preview['new_total'],
                'cumulative_with_pix' => (bool) Setting::get('referral_cumulative_with_pix', true),
                'message' => 'Desconto de indicação de ' . $preview['affiliate_name'] . ' aplicado!',
            ]);
        }

        $coupon = $resolved['model'];

        if (! $coupon->isValid()) {
            return response()->json(['success' => false, 'message' => 'Cupom inválido ou expirado.']);
        }

        $document = $request->input('payer_document');
        if (! $coupon->isAvailableForDocument($document)) {
            $customerDoc = auth('customer')->user()?->document;
            if (! $coupon->isAvailableForDocument($customerDoc)) {
                return response()->json(['success' => false, 'message' => 'Este cupom não está disponível para você.']);
            }
        }

        $discount = $coupon->calculateDiscount($baseTotal);

        return response()->json([
            'success' => true,
            'type' => 'coupon',
            'coupon_code' => $coupon->code,
            'discount_amount' => round($discount, 2),
            'new_total' => round($baseTotal - $discount, 2),
            'cumulative_with_pix' => (bool) $coupon->cumulative_with_pix,
            'message' => 'Cupom aplicado!',
        ]);
    }

    private function calculateBaseTotal(Order $order): float
    {
        $total = 0;
        foreach ($order->flights as $flight) {
            $total += (float) ($flight->money_price ?? 0);
            $total += (float) ($flight->tax ?? 0);
        }

        return round($total, 2);
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
