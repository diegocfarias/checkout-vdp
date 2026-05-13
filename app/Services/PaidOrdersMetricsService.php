<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFlight;
use App\Models\OrderPayment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaidOrdersMetricsService
{
    public function dashboard(array $filters): array
    {
        $range = $this->dateRange($filters);
        $orders = $this->paidOrders($filters);
        $metrics = $orders
            ->map(fn (Order $order): array => $this->calculateOrderMetrics($order, $filters))
            ->values();

        $totals = $this->totals($metrics);

        return [
            'totals' => $totals,
            'stats' => $this->stats($totals),
            'timeline' => $this->timeline($metrics, $range['from'], $range['to']),
            'payment_methods' => $this->paymentMethods($metrics),
            'gateways' => $this->gateways($metrics),
            'airlines' => $this->airlines($metrics),
            'statuses' => $this->statuses($metrics),
            'routes' => $this->routes($metrics),
            'coupons' => $this->coupons($metrics),
            'issuers' => $this->issuers($metrics),
        ];
    }

    public function paidOrdersQuery(array $filters): Builder
    {
        $query = Order::query();

        $this->applyPaidFilter($query, $filters);
        $this->applyOrderFilters($query, $filters);

        return $query;
    }

    public function calculateOrderMetrics(Order $order, array $filters = []): array
    {
        $order->loadMissing(['payments', 'flights', 'emission.issuer', 'coupon']);

        $payments = $this->matchingPaidPayments($order, $filters);
        $gross = $this->orderGross($order);
        $discount = $this->number($order->discount_amount);
        $wallet = $this->number($order->wallet_amount_used);
        $netBeforeWallet = max($gross - $discount, 0);

        $hasKnownPaymentAmount = $payments->contains(fn (OrderPayment $payment): bool => $payment->amount !== null);
        $externalRevenue = $payments->sum(fn (OrderPayment $payment): float => $this->number($payment->amount));

        if ($payments->isNotEmpty() && ! $hasKnownPaymentAmount) {
            $externalRevenue = max($netBeforeWallet - $wallet, 0);
        }

        if ($payments->isEmpty() && $this->fallbackOrderPaidInRange($order, $filters)) {
            $externalRevenue = max($netBeforeWallet - $wallet, 0);
        }

        $gmv = $externalRevenue + $wallet;

        if ($gmv <= 0 && $netBeforeWallet > 0) {
            $gmv = $netBeforeWallet;
        }

        $milesCost = $this->milesCost($order);
        $boardingTaxCost = $this->boardingTaxCost($order);
        $emissionCost = $this->number($order->emission?->emission_value);
        $totalCost = $milesCost + $boardingTaxCost + $emissionCost;
        $margin = $gmv - $totalCost;
        $paidAt = $this->paidAt($order, $payments);
        $paymentRows = $this->paymentRows($payments, $externalRevenue);

        return [
            'order_id' => $order->id,
            'tracking_code' => $order->tracking_code,
            'status' => $order->status,
            'status_label' => $this->orderStatusLabel($order->status),
            'paid_at' => $paidAt?->toDateTimeString(),
            'gross' => round($gross, 2),
            'discount' => round($discount, 2),
            'wallet' => round($wallet, 2),
            'external_revenue' => round($externalRevenue, 2),
            'gmv' => round($gmv, 2),
            'passengers' => $this->payingPassengers($order),
            'miles_cost' => round($milesCost, 2),
            'boarding_tax_cost' => round($boardingTaxCost, 2),
            'emission_cost' => round($emissionCost, 2),
            'total_cost' => round($totalCost, 2),
            'margin' => round($margin, 2),
            'margin_rate' => $gmv > 0 ? round(($margin / $gmv) * 100, 2) : 0.0,
            'route' => $this->routeLabel($order),
            'departure_iata' => $order->departure_iata,
            'arrival_iata' => $order->arrival_iata,
            'coupon_code' => $order->coupon?->code,
            'issuer_name' => $order->emission?->issuer?->name,
            'emission_status' => $order->emission?->status,
            'payments' => $paymentRows,
            'flights' => $order->flights
                ->map(fn (OrderFlight $flight): array => [
                    'cia' => strtoupper((string) ($flight->cia ?: 'Sem cia')),
                    'gross' => round($this->flightGross($flight) * $this->payingPassengers($order), 2),
                ])
                ->values()
                ->all(),
        ];
    }

    public function formatMoney(float|int|null $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    public function formatPercent(float|int|null $value): string
    {
        return number_format((float) $value, 1, ',', '.').'%';
    }

    public function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'pix' => 'Pix',
            'credit_card' => 'Cartão',
            'boleto' => 'Boleto',
            null, '', 'unknown' => 'Não informado',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function gatewayLabel(?string $gateway): string
    {
        return match ($gateway) {
            null, '', 'unknown' => 'Não informado',
            default => ucfirst(str_replace('_', ' ', $gateway)),
        };
    }

    public function orderStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'awaiting_payment' => 'Aguardando pagamento',
            'awaiting_emission' => 'Aguardando emissão',
            'completed' => 'Emitido',
            'cancelled' => 'Cancelado',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Não informado',
        };
    }

    private function paidOrders(array $filters): Collection
    {
        return $this->paidOrdersQuery($filters)
            ->with(['payments', 'flights', 'emission.issuer', 'coupon', 'customer'])
            ->get();
    }

    private function totals(Collection $metrics): array
    {
        $orders = $metrics->count();
        $gmv = $metrics->sum('gmv');
        $externalRevenue = $metrics->sum('external_revenue');
        $totalCost = $metrics->sum('total_cost');
        $margin = $metrics->sum('margin');
        $paymentRows = $metrics->flatMap(fn (array $metric): array => $metric['payments']);

        return [
            'orders' => $orders,
            'passengers' => $metrics->sum('passengers'),
            'gross' => round($metrics->sum('gross'), 2),
            'gmv' => round($gmv, 2),
            'external_revenue' => round($externalRevenue, 2),
            'discount' => round($metrics->sum('discount'), 2),
            'wallet' => round($metrics->sum('wallet'), 2),
            'avg_ticket' => $orders > 0 ? round($gmv / $orders, 2) : 0.0,
            'miles_cost' => round($metrics->sum('miles_cost'), 2),
            'boarding_tax_cost' => round($metrics->sum('boarding_tax_cost'), 2),
            'emission_cost' => round($metrics->sum('emission_cost'), 2),
            'total_cost' => round($totalCost, 2),
            'margin' => round($margin, 2),
            'margin_rate' => $gmv > 0 ? round(($margin / $gmv) * 100, 2) : 0.0,
            'pix_revenue' => round($paymentRows->where('method', 'pix')->sum('amount'), 2),
            'card_revenue' => round($paymentRows->where('method', 'credit_card')->sum('amount'), 2),
            'completed_emissions' => $metrics->where('emission_status', 'completed')->count(),
        ];
    }

    private function stats(array $totals): array
    {
        return [
            ['label' => 'Pedidos pagos', 'value' => (string) $totals['orders'], 'icon' => 'heroicon-o-shopping-bag', 'hint' => $totals['passengers'].' passageiros'],
            ['label' => 'GMV', 'value' => $this->formatMoney($totals['gmv']), 'icon' => 'heroicon-o-chart-bar-square', 'hint' => 'líquido com wallet'],
            ['label' => 'Receita capturada', 'value' => $this->formatMoney($totals['external_revenue']), 'icon' => 'heroicon-o-banknotes', 'hint' => 'pagamentos externos'],
            ['label' => 'Margem bruta', 'value' => $this->formatMoney($totals['margin']), 'icon' => 'heroicon-o-arrow-trending-up', 'hint' => $this->formatPercent($totals['margin_rate'])],
            ['label' => 'Ticket médio', 'value' => $this->formatMoney($totals['avg_ticket']), 'icon' => 'heroicon-o-receipt-percent', 'hint' => 'por pedido'],
            ['label' => 'Descontos', 'value' => $this->formatMoney($totals['discount']), 'icon' => 'heroicon-o-tag', 'hint' => 'cupons aplicados'],
            ['label' => 'Wallet usado', 'value' => $this->formatMoney($totals['wallet']), 'icon' => 'heroicon-o-wallet', 'hint' => 'saldo consumido'],
            ['label' => 'Custo total', 'value' => $this->formatMoney($totals['total_cost']), 'icon' => 'heroicon-o-calculator', 'hint' => 'milhas + taxas + emissão'],
            ['label' => 'Taxas pagas', 'value' => $this->formatMoney($totals['boarding_tax_cost']), 'icon' => 'heroicon-o-document-currency-dollar', 'hint' => 'taxa de embarque'],
            ['label' => 'Custo milhas', 'value' => $this->formatMoney($totals['miles_cost']), 'icon' => 'heroicon-o-paper-airplane', 'hint' => 'pelo milheiro'],
            ['label' => 'Pix', 'value' => $this->formatMoney($totals['pix_revenue']), 'icon' => 'heroicon-o-qr-code', 'hint' => 'receita capturada'],
            ['label' => 'Cartão', 'value' => $this->formatMoney($totals['card_revenue']), 'icon' => 'heroicon-o-credit-card', 'hint' => 'receita capturada'],
        ];
    }

    private function timeline(Collection $metrics, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $monthly = $from->diffInDays($to) > 62;
        $buckets = [];

        if ($monthly) {
            $cursor = $from->startOfMonth();
            while ($cursor->lte($to)) {
                $key = $cursor->format('Y-m');
                $buckets[$key] = $this->emptyTimelineBucket($cursor->format('m/Y'));
                $cursor = $cursor->addMonth();
            }
        } else {
            foreach (CarbonPeriod::create($from->startOfDay(), '1 day', $to->startOfDay()) as $day) {
                $buckets[$day->format('Y-m-d')] = $this->emptyTimelineBucket($day->format('d/m'));
            }
        }

        foreach ($metrics as $metric) {
            $paidAt = $metric['paid_at'] ? CarbonImmutable::parse($metric['paid_at']) : $from;
            $key = $monthly ? $paidAt->format('Y-m') : $paidAt->format('Y-m-d');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['orders']++;
            $buckets[$key]['gmv'] += $metric['gmv'];
            $buckets[$key]['external_revenue'] += $metric['external_revenue'];
            $buckets[$key]['margin'] += $metric['margin'];
        }

        $items = collect($buckets)
            ->map(function (array $bucket): array {
                $bucket['gmv'] = round($bucket['gmv'], 2);
                $bucket['external_revenue'] = round($bucket['external_revenue'], 2);
                $bucket['margin'] = round($bucket['margin'], 2);

                return $bucket;
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'max' => max(1, collect($items)->max('gmv'), collect($items)->max('external_revenue'), collect($items)->max('margin')),
        ];
    }

    private function paymentMethods(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            foreach ($metric['payments'] as $payment) {
                $method = $payment['method'] ?: 'unknown';
                $rows[$method] ??= [
                    'label' => $this->paymentMethodLabel($method),
                    'method' => $method,
                    'amount' => 0.0,
                    'orders' => 0,
                ];
                $rows[$method]['amount'] += $payment['amount'];
                $rows[$method]['orders']++;
            }
        }

        return $this->rankedRows($rows, 'amount');
    }

    private function gateways(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            foreach ($metric['payments'] as $payment) {
                $gateway = $payment['gateway'] ?: 'unknown';
                $rows[$gateway] ??= [
                    'label' => $this->gatewayLabel($gateway),
                    'gateway' => $gateway,
                    'amount' => 0.0,
                    'orders' => 0,
                ];
                $rows[$gateway]['amount'] += $payment['amount'];
                $rows[$gateway]['orders']++;
            }
        }

        return $this->rankedRows($rows, 'amount');
    }

    private function airlines(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            $flightGross = collect($metric['flights'])->sum('gross');
            $flightCount = max(1, count($metric['flights']));

            foreach ($metric['flights'] as $flight) {
                $cia = $flight['cia'] ?: 'Sem cia';
                $share = $flightGross > 0 ? $flight['gross'] / $flightGross : 1 / $flightCount;

                $rows[$cia] ??= [
                    'label' => $cia,
                    'amount' => 0.0,
                    'margin' => 0.0,
                    'flights' => 0,
                ];
                $rows[$cia]['amount'] += $metric['gmv'] * $share;
                $rows[$cia]['margin'] += $metric['margin'] * $share;
                $rows[$cia]['flights']++;
            }
        }

        return $this->rankedRows($rows, 'amount', 8);
    }

    private function statuses(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            $status = $metric['status'] ?: 'unknown';
            $rows[$status] ??= [
                'label' => $metric['status_label'],
                'status' => $status,
                'orders' => 0,
                'amount' => 0.0,
            ];
            $rows[$status]['orders']++;
            $rows[$status]['amount'] += $metric['gmv'];
        }

        return $this->rankedRows($rows, 'orders', 8);
    }

    private function routes(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            $route = $metric['route'];
            $rows[$route] ??= [
                'label' => $route,
                'orders' => 0,
                'amount' => 0.0,
                'margin' => 0.0,
            ];
            $rows[$route]['orders']++;
            $rows[$route]['amount'] += $metric['gmv'];
            $rows[$route]['margin'] += $metric['margin'];
        }

        return $this->rankedRows($rows, 'amount', 10);
    }

    private function coupons(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            if (! $metric['coupon_code']) {
                continue;
            }

            $code = strtoupper($metric['coupon_code']);
            $rows[$code] ??= [
                'label' => $code,
                'orders' => 0,
                'amount' => 0.0,
                'discount' => 0.0,
            ];
            $rows[$code]['orders']++;
            $rows[$code]['amount'] += $metric['gmv'];
            $rows[$code]['discount'] += $metric['discount'];
        }

        return $this->rankedRows($rows, 'discount', 10);
    }

    private function issuers(Collection $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            if (! $metric['issuer_name']) {
                continue;
            }

            $issuer = $metric['issuer_name'];
            $rows[$issuer] ??= [
                'label' => $issuer,
                'orders' => 0,
                'amount' => 0.0,
                'cost' => 0.0,
                'margin' => 0.0,
            ];
            $rows[$issuer]['orders']++;
            $rows[$issuer]['amount'] += $metric['gmv'];
            $rows[$issuer]['cost'] += $metric['total_cost'];
            $rows[$issuer]['margin'] += $metric['margin'];
        }

        return $this->rankedRows($rows, 'amount', 10);
    }

    private function applyPaidFilter(Builder $query, array $filters): void
    {
        $range = $this->dateRange($filters);
        $hasPaymentFacet = $this->filledChoice($filters['payment_method'] ?? null)
            || $this->filledChoice($filters['gateway'] ?? null);

        $query->where(function (Builder $paidQuery) use ($filters, $range, $hasPaymentFacet): void {
            $paidQuery->whereHas('payments', function (Builder $paymentQuery) use ($filters, $range): void {
                $paymentQuery->where('status', 'paid');
                $this->applyPaymentDateFilter($paymentQuery, $range['from'], $range['to']);
                $this->applyPaymentFilters($paymentQuery, $filters);
            });

            if (! $hasPaymentFacet) {
                $paidQuery->orWhere(function (Builder $fallbackQuery) use ($range): void {
                    $fallbackQuery
                        ->whereDoesntHave('payments', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', 'paid'))
                        ->whereBetween('paid_at', [$range['from'], $range['to']]);
                });
            }
        });
    }

    private function applyPaymentDateFilter(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where(function (Builder $dateQuery) use ($from, $to): void {
            $dateQuery
                ->whereBetween('paid_at', [$from, $to])
                ->orWhere(function (Builder $nullDateQuery) use ($from, $to): void {
                    $nullDateQuery
                        ->whereNull('paid_at')
                        ->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->whereBetween('paid_at', [$from, $to]));
                });
        });
    }

    private function applyPaymentFilters(Builder $query, array $filters): void
    {
        if ($this->filledChoice($filters['payment_method'] ?? null)) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if ($this->filledChoice($filters['gateway'] ?? null)) {
            $query->where('gateway', $filters['gateway']);
        }
    }

    private function applyOrderFilters(Builder $query, array $filters): void
    {
        if ($this->filledChoice($filters['order_status'] ?? null)) {
            $query->where('status', $filters['order_status']);
        }

        if ($this->filledChoice($filters['airline'] ?? null)) {
            $query->whereHas('flights', fn (Builder $flightQuery): Builder => $flightQuery->where('cia', $filters['airline']));
        }

        if ($this->filledChoice($filters['device_type'] ?? null)) {
            $query->where('device_type', $filters['device_type']);
        }

        if ($this->filledChoice($filters['issuer_id'] ?? null)) {
            $query->whereHas('emission', fn (Builder $emissionQuery): Builder => $emissionQuery->where('issuer_id', $filters['issuer_id']));
        }

        if ($this->filledChoice($filters['coupon'] ?? null)) {
            match ($filters['coupon']) {
                'with_coupon' => $query->whereNotNull('coupon_id'),
                'without_coupon' => $query->whereNull('coupon_id'),
                default => $query->where('coupon_id', $filters['coupon']),
            };
        }

        if (! empty($filters['departure_iata'])) {
            $query->where('departure_iata', strtoupper(trim((string) $filters['departure_iata'])));
        }

        if (! empty($filters['arrival_iata'])) {
            $query->where('arrival_iata', strtoupper(trim((string) $filters['arrival_iata'])));
        }
    }

    private function matchingPaidPayments(Order $order, array $filters): Collection
    {
        $range = $this->dateRange($filters);

        return $order->payments
            ->filter(function (OrderPayment $payment) use ($order, $filters, $range): bool {
                if ($payment->status !== 'paid') {
                    return false;
                }

                if ($this->filledChoice($filters['payment_method'] ?? null) && $payment->payment_method !== $filters['payment_method']) {
                    return false;
                }

                if ($this->filledChoice($filters['gateway'] ?? null) && $payment->gateway !== $filters['gateway']) {
                    return false;
                }

                $paidAt = $payment->paid_at ?? $order->paid_at;

                if (! $paidAt) {
                    return false;
                }

                return $paidAt->betweenIncluded($range['from'], $range['to']);
            })
            ->values();
    }

    private function fallbackOrderPaidInRange(Order $order, array $filters): bool
    {
        if ($order->payments->where('status', 'paid')->isNotEmpty() || ! $order->paid_at) {
            return false;
        }

        $range = $this->dateRange($filters);

        return $order->paid_at->betweenIncluded($range['from'], $range['to']);
    }

    private function orderGross(Order $order): float
    {
        $pax = $this->payingPassengers($order);

        return $order->flights->sum(fn (OrderFlight $flight): float => $this->flightGross($flight) * $pax);
    }

    private function flightGross(OrderFlight $flight): float
    {
        return $this->flightMoney($flight) + $this->flightTax($flight);
    }

    private function flightMoney(OrderFlight $flight): float
    {
        $money = $this->number($flight->money_price);

        return $money > 0 ? $money : $this->number($flight->price_money);
    }

    private function flightTax(OrderFlight $flight): float
    {
        $tax = $this->number($flight->tax);

        return $tax > 0 ? $tax : $this->number($flight->boarding_tax);
    }

    private function milesCost(Order $order): float
    {
        $costPerThousand = $this->number($order->emission?->miles_cost_per_thousand);

        if ($costPerThousand <= 0) {
            return 0.0;
        }

        $miles = $order->flights->sum(function (OrderFlight $flight): float {
            $miles = $this->number($flight->price_miles);

            return $miles > 0 ? $miles : $this->number($flight->miles_price);
        });

        return ($miles / 1000) * $costPerThousand;
    }

    private function boardingTaxCost(Order $order): float
    {
        return $order->flights->sum(function (OrderFlight $flight): float {
            if ($flight->paid_boarding_tax !== null) {
                return $this->number($flight->paid_boarding_tax);
            }

            return $this->flightTax($flight);
        });
    }

    private function payingPassengers(Order $order): int
    {
        return max(1, (int) $order->total_adults + (int) $order->total_children);
    }

    private function paidAt(Order $order, Collection $payments): ?CarbonInterface
    {
        $payment = $payments
            ->filter(fn (OrderPayment $payment): bool => $payment->paid_at !== null)
            ->sortByDesc('paid_at')
            ->first();

        return $payment?->paid_at ?? $order->paid_at;
    }

    private function paymentRows(Collection $payments, float $externalRevenue): array
    {
        $rows = $payments
            ->map(fn (OrderPayment $payment): array => [
                'method' => $payment->payment_method ?: 'unknown',
                'gateway' => $payment->gateway ?: 'unknown',
                'amount' => round($this->number($payment->amount), 2),
            ])
            ->values()
            ->all();

        $amount = collect($rows)->sum('amount');

        if ($rows !== [] && $amount <= 0 && $externalRevenue > 0) {
            $rows[0]['amount'] = round($externalRevenue, 2);
        }

        if ($rows === [] && $externalRevenue > 0) {
            $rows[] = [
                'method' => 'unknown',
                'gateway' => 'unknown',
                'amount' => round($externalRevenue, 2),
            ];
        }

        return $rows;
    }

    private function routeLabel(Order $order): string
    {
        $departure = strtoupper((string) ($order->departure_iata ?: '---'));
        $arrival = strtoupper((string) ($order->arrival_iata ?: '---'));

        return "{$departure} -> {$arrival}";
    }

    private function emptyTimelineBucket(string $label): array
    {
        return [
            'label' => $label,
            'orders' => 0,
            'gmv' => 0.0,
            'external_revenue' => 0.0,
            'margin' => 0.0,
        ];
    }

    private function rankedRows(array $rows, string $sortKey, int $limit = 6): array
    {
        usort($rows, fn (array $a, array $b): int => ($b[$sortKey] <=> $a[$sortKey]));

        $rows = array_slice($rows, 0, $limit);
        $max = max(1, collect($rows)->max($sortKey) ?: 0);

        return collect($rows)
            ->map(function (array $row) use ($sortKey, $max): array {
                foreach (['amount', 'margin', 'discount', 'cost'] as $moneyKey) {
                    if (isset($row[$moneyKey])) {
                        $row[$moneyKey] = round($row[$moneyKey], 2);
                    }
                }

                $row['share'] = round(((float) ($row[$sortKey] ?? 0) / $max) * 100, 2);

                return $row;
            })
            ->values()
            ->all();
    }

    private function dateRange(array $filters): array
    {
        $from = ! empty($filters['date_from'])
            ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth()->toImmutable();

        $to = ! empty($filters['date_to'])
            ? CarbonImmutable::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay()->toImmutable();

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return ['from' => $from, 'to' => $to];
    }

    private function filledChoice(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== 'all';
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', (string) $value);

        if ($normalized === '' || $normalized === '-' || $normalized === null) {
            return 0.0;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }
}
