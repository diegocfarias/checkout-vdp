<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderFlight;
use App\Services\CheckoutPricingService;
use Tests\TestCase;

class CheckoutPricingServiceTest extends TestCase
{
    public function test_order_totals_use_only_adults_and_children_as_paying_passengers(): void
    {
        $order = $this->orderWithFlights(
            adults: 2,
            children: 1,
            infants: 1,
            flights: [
                [300, 40],
                [200, 60],
            ],
        );

        $pricing = app(CheckoutPricingService::class);

        $this->assertSame(3, $pricing->payingPax($order));
        $this->assertEqualsWithDelta(1500.00, $pricing->fareTotal($order), 0.01);
        $this->assertEqualsWithDelta(300.00, $pricing->taxTotal($order), 0.01);
        $this->assertEqualsWithDelta(1800.00, $pricing->grossTotal($order), 0.01);
        $this->assertEqualsWithDelta(1500.00, $pricing->discountableTotal($order), 0.01);
    }

    public function test_pix_discount_applies_only_to_fare_not_to_tax(): void
    {
        $breakdown = app(CheckoutPricingService::class)->calculate(
            $this->orderWithFlights(flights: [[1000, 100]]),
            [
                'payment_method' => 'pix',
                'pix_discount_pct' => 3,
            ],
        );

        $this->assertMoney(1000.00, $breakdown['fare_total']);
        $this->assertMoney(100.00, $breakdown['tax_total']);
        $this->assertMoney(30.00, $breakdown['pix_discount_amount']);
        $this->assertMoney(1070.00, $breakdown['payable_total']);
        $this->assertMoney(1070.00, $breakdown['total_with_interest']);
    }

    public function test_coupon_blocks_pix_and_discounts_only_fare(): void
    {
        $breakdown = app(CheckoutPricingService::class)->calculate(
            $this->orderWithFlights(flights: [[1000, 100]]),
            [
                'payment_method' => 'pix',
                'discount_amount' => 100,
                'pix_discount_pct' => 3,
                'can_apply_pix_discount' => false,
            ],
        );

        $this->assertMoney(100.00, $breakdown['discount_amount']);
        $this->assertMoney(0.00, $breakdown['pix_discount_amount']);
        $this->assertMoney(1000.00, $breakdown['payable_total']);
    }

    public function test_discount_can_remove_all_fare_but_never_removes_tax(): void
    {
        $breakdown = app(CheckoutPricingService::class)->calculate(
            $this->orderWithFlights(flights: [[1000, 100]]),
            [
                'payment_method' => 'pix',
                'discount_amount' => 5000,
                'pix_discount_pct' => 3,
                'can_apply_pix_discount' => false,
            ],
        );

        $this->assertMoney(1000.00, $breakdown['discount_amount']);
        $this->assertMoney(100.00, $breakdown['payable_total']);
    }

    public function test_wallet_reduces_total_and_pix_applies_to_remaining_fare(): void
    {
        $breakdown = app(CheckoutPricingService::class)->calculate(
            $this->orderWithFlights(flights: [[1000, 100]]),
            [
                'payment_method' => 'pix',
                'wallet_amount' => 200,
                'pix_discount_pct' => 3,
            ],
        );

        $this->assertMoney(200.00, $breakdown['wallet_amount']);
        $this->assertMoney(27.00, $breakdown['pix_discount_amount']);
        $this->assertMoney(873.00, $breakdown['payable_total']);
        $this->assertMoney(873.00, $breakdown['total_with_interest']);
    }

    public function test_credit_card_interest_applies_after_commercial_discount(): void
    {
        $breakdown = app(CheckoutPricingService::class)->calculate(
            $this->orderWithFlights(flights: [[1000, 100]]),
            [
                'payment_method' => 'credit_card',
                'discount_amount' => 100,
                'interest_rate' => 10,
            ],
        );

        $this->assertMoney(1000.00, $breakdown['payable_total']);
        $this->assertMoney(100.00, $breakdown['interest_amount']);
        $this->assertMoney(1100.00, $breakdown['total_with_interest']);
    }

    public function test_coupon_model_limits_discounts_to_discountable_fare(): void
    {
        $fixed = new Coupon([
            'type' => 'fixed',
            'value' => 1500,
        ]);
        $percentWithCap = new Coupon([
            'type' => 'percent',
            'value' => 20,
            'max_discount' => 150,
        ]);

        $this->assertMoney(1000.00, $fixed->calculateDiscount(1000));
        $this->assertMoney(150.00, $percentWithCap->calculateDiscount(1000));
    }

    private function orderWithFlights(
        int $adults = 1,
        int $children = 0,
        int $infants = 0,
        array $flights = [[100, 30]],
    ): Order {
        $order = new Order([
            'total_adults' => $adults,
            'total_children' => $children,
            'total_babies' => $infants,
        ]);

        $order->setRelation('flights', collect(array_map(
            fn (array $flight): OrderFlight => new OrderFlight([
                'money_price' => $flight[0],
                'tax' => $flight[1],
            ]),
            $flights,
        )));

        return $order;
    }

    private function assertMoney(float $expected, float $actual): void
    {
        $this->assertEqualsWithDelta($expected, $actual, 0.01);
    }
}
