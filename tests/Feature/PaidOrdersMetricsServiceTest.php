<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderEmission;
use App\Models\OrderFlight;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\PaidOrdersMetricsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class PaidOrdersMetricsServiceTest extends TestCase
{
    public function test_it_calculates_paid_order_financial_metrics(): void
    {
        $issuer = new User([
            'name' => 'Emissor Teste',
        ]);
        $issuer->id = 10;

        $coupon = new Coupon([
            'code' => 'TESTE10',
            'type' => 'fixed',
            'value' => 30,
            'active' => true,
        ]);

        $order = new Order([
            'total_adults' => 2,
            'total_children' => 1,
            'total_babies' => 0,
            'discount_amount' => 30,
            'wallet_amount_used' => 20,
            'cabin' => 'EC',
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'status' => 'completed',
            'expires_at' => '2026-05-20 10:00:00',
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        $order->id = 1;
        $order->tracking_code = 'VDP-TEST';

        $flight = new OrderFlight([
            'direction' => 'outbound',
            'cia' => 'GOL',
            'money_price' => '100.00',
            'tax' => '20.00',
            'paid_boarding_tax' => 18,
            'price_miles' => '10.000',
        ]);

        $payment = new OrderPayment([
            'gateway' => 'appmax',
            'status' => 'paid',
            'payment_method' => 'pix',
            'amount' => 310,
            'currency' => 'BRL',
            'paid_at' => '2026-05-10 10:00:00',
        ]);

        $emission = new OrderEmission([
            'status' => 'completed',
            'emission_value' => 15,
            'miles_cost_per_thousand' => 25,
            'completed_at' => '2026-05-10 12:00:00',
        ]);
        $emission->setRelation('issuer', $issuer);

        $order->setRelation('flights', new EloquentCollection([$flight]));
        $order->setRelation('payments', new EloquentCollection([$payment]));
        $order->setRelation('emission', $emission);
        $order->setRelation('coupon', $coupon);

        $metric = app(PaidOrdersMetricsService::class)->calculateOrderMetrics($order, [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]);

        $this->assertEqualsWithDelta(360, $metric['gross'], 0.01);
        $this->assertEqualsWithDelta(330, $metric['gmv'], 0.01);
        $this->assertEqualsWithDelta(310, $metric['external_revenue'], 0.01);
        $this->assertEqualsWithDelta(30, $metric['discount'], 0.01);
        $this->assertEqualsWithDelta(20, $metric['wallet'], 0.01);
        $this->assertEqualsWithDelta(250, $metric['miles_cost'], 0.01);
        $this->assertEqualsWithDelta(18, $metric['boarding_tax_cost'], 0.01);
        $this->assertEqualsWithDelta(283, $metric['total_cost'], 0.01);
        $this->assertEqualsWithDelta(47, $metric['margin'], 0.01);
        $this->assertSame('GRU -> SDU', $metric['route']);
        $this->assertSame('TESTE10', $metric['coupon_code']);
        $this->assertSame('Emissor Teste', $metric['issuer_name']);
    }
}
