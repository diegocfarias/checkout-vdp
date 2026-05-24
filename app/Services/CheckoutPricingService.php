<?php

namespace App\Services;

use App\Models\Order;

class CheckoutPricingService
{
    public function payingPax(Order $order): int
    {
        return max(1, (int) $order->total_adults + (int) $order->total_children);
    }

    public function fareTotal(Order $order): float
    {
        $payingPax = $this->payingPax($order);
        $total = 0.0;

        foreach ($order->flights as $flight) {
            $total += (float) ($flight->money_price ?? 0) * $payingPax;
        }

        return round($total, 2);
    }

    public function taxTotal(Order $order): float
    {
        $payingPax = $this->payingPax($order);
        $total = 0.0;

        foreach ($order->flights as $flight) {
            $total += (float) ($flight->tax ?? 0) * $payingPax;
        }

        return round($total, 2);
    }

    public function grossTotal(Order $order): float
    {
        return round($this->fareTotal($order) + $this->taxTotal($order), 2);
    }

    public function discountableTotal(Order $order): float
    {
        return $this->fareTotal($order);
    }

    /**
     * @return array{
     *     fare_total: float,
     *     tax_total: float,
     *     gross_total: float,
     *     discountable_total: float,
     *     discount_amount: float,
     *     wallet_amount: float,
     *     pix_discount_amount: float,
     *     interest_amount: float,
     *     payable_total: float,
     *     total_with_interest: float
     * }
     */
    public function calculate(Order $order, array $options = []): array
    {
        $fareTotal = $this->fareTotal($order);
        $taxTotal = $this->taxTotal($order);
        $grossTotal = round($fareTotal + $taxTotal, 2);
        $discountableTotal = $fareTotal;

        $discountAmount = min(
            max(round((float) ($options['discount_amount'] ?? 0), 2), 0),
            $discountableTotal,
        );

        $walletAmount = min(
            max(round((float) ($options['wallet_amount'] ?? 0), 2), 0),
            max(round($grossTotal - $discountAmount, 2), 0),
        );

        $paymentMethod = (string) ($options['payment_method'] ?? 'pix');
        $canApplyPixDiscount = (bool) ($options['can_apply_pix_discount'] ?? true);
        $pixDiscountPct = max((float) ($options['pix_discount_pct'] ?? 0), 0);

        $payableBeforePaymentDiscount = max(round($grossTotal - $discountAmount - $walletAmount, 2), 0);
        $pixDiscountAmount = 0.0;

        if ($paymentMethod === 'pix' && $canApplyPixDiscount && $pixDiscountPct > 0) {
            $remainingDiscountableTotal = max(round($discountableTotal - $discountAmount, 2), 0);
            $pixDiscountBase = min($remainingDiscountableTotal, $payableBeforePaymentDiscount);
            $pixDiscountAmount = round($pixDiscountBase * ($pixDiscountPct / 100), 2);
        }

        $payableTotal = max(round($payableBeforePaymentDiscount - $pixDiscountAmount, 2), 0);

        $interestRate = $paymentMethod === 'credit_card'
            ? max((float) ($options['interest_rate'] ?? 0), 0)
            : 0.0;
        $interestAmount = round($payableTotal * ($interestRate / 100), 2);
        $totalWithInterest = round($payableTotal + $interestAmount, 2);

        return [
            'fare_total' => $fareTotal,
            'tax_total' => $taxTotal,
            'gross_total' => $grossTotal,
            'discountable_total' => $discountableTotal,
            'discount_amount' => $discountAmount,
            'wallet_amount' => $walletAmount,
            'pix_discount_amount' => $pixDiscountAmount,
            'interest_amount' => $interestAmount,
            'payable_total' => $payableTotal,
            'total_with_interest' => $totalWithInterest,
        ];
    }
}
