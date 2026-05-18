<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use App\Services\PaidOrdersMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class PaidOrdersMetricsDashboardTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_aggregates_paid_orders_fallbacks_and_facets(): void
    {
        Carbon::setTestNow('2026-05-16 12:00:00');

        $issuer = User::create([
            'name' => 'Emissor Um',
            'email' => 'emissor@example.com',
            'password' => 'secret',
            'role' => 'emissor',
        ]);
        $coupon = Coupon::create([
            'code' => 'TESTE10',
            'type' => 'fixed',
            'value' => 30,
            'active' => true,
        ]);

        $paidWithPix = $this->createOrder([
            'status' => 'completed',
            'total_adults' => 2,
            'total_children' => 1,
            'discount_amount' => 30,
            'wallet_amount_used' => 20,
            'coupon_id' => $coupon->id,
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'device_type' => 'mobile',
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        $this->addFlight($paidWithPix, [
            'cia' => 'GOL',
            'money_price' => '100.00',
            'tax' => '20.00',
            'paid_boarding_tax' => 18,
            'price_miles' => '10.000',
        ]);
        $this->addPayment($paidWithPix, [
            'gateway' => 'appmax',
            'payment_method' => 'pix',
            'status' => 'paid',
            'amount' => 310,
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        $paidWithPix->emission()->create([
            'issuer_id' => $issuer->id,
            'status' => 'completed',
            'emission_value' => 15,
            'miles_cost_per_thousand' => 25,
            'completed_at' => '2026-05-10 12:00:00',
        ]);

        $fallbackPaid = $this->createOrder([
            'status' => 'awaiting_emission',
            'total_adults' => 1,
            'total_children' => 0,
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
            'device_type' => 'desktop',
            'paid_at' => '2026-05-12 08:00:00',
        ]);
        $this->addFlight($fallbackPaid, [
            'cia' => 'LATAM',
            'money_price' => '200.00',
            'tax' => '40.00',
            'price_miles' => '0',
        ]);

        $outsideRange = $this->createOrder([
            'status' => 'completed',
            'departure_iata' => 'BSB',
            'arrival_iata' => 'SSA',
            'paid_at' => '2026-04-30 23:00:00',
        ]);
        $this->addFlight($outsideRange, [
            'money_price' => '999.00',
            'tax' => '99.00',
        ]);
        $this->addPayment($outsideRange, [
            'status' => 'paid',
            'amount' => 1098,
            'paid_at' => '2026-04-30 23:00:00',
        ]);

        $dashboard = app(PaidOrdersMetricsService::class)->dashboard([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]);

        $this->assertSame(2, $dashboard['totals']['orders']);
        $this->assertSame(4, $dashboard['totals']['passengers']);
        $this->assertEqualsWithDelta(600, $dashboard['totals']['gross'], 0.01);
        $this->assertEqualsWithDelta(570, $dashboard['totals']['gmv'], 0.01);
        $this->assertEqualsWithDelta(550, $dashboard['totals']['external_revenue'], 0.01);
        $this->assertEqualsWithDelta(323, $dashboard['totals']['total_cost'], 0.01);
        $this->assertEqualsWithDelta(247, $dashboard['totals']['margin'], 0.01);
        $this->assertEqualsWithDelta(310, $dashboard['totals']['pix_revenue'], 0.01);
        $this->assertSame(1, $dashboard['totals']['completed_emissions']);

        $this->assertSame('Pedidos pagos', $dashboard['stats'][0]['label']);
        $this->assertSame('2', $dashboard['stats'][0]['value']);
        $this->assertSame(31, count($dashboard['timeline']['items']));
        $this->assertSame(330.0, $dashboard['timeline']['max']);
        $this->assertSame('Pix', $dashboard['payment_methods'][0]['label']);
        $this->assertSame('Não informado', $dashboard['payment_methods'][1]['label']);
        $this->assertSame('Appmax', $dashboard['gateways'][0]['label']);
        $this->assertSame('GOL', $dashboard['airlines'][0]['label']);
        $this->assertSame('LATAM', $dashboard['airlines'][1]['label']);
        $this->assertSame('Emitido', $dashboard['statuses'][0]['label']);
        $this->assertSame('GRU -> SDU', $dashboard['routes'][0]['label']);
        $this->assertSame('TESTE10', $dashboard['coupons'][0]['label']);
        $this->assertSame('Emissor Um', $dashboard['issuers'][0]['label']);

        $pixOnly = app(PaidOrdersMetricsService::class)->dashboard([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'payment_method' => 'pix',
        ]);

        $this->assertSame(1, $pixOnly['totals']['orders']);
        $this->assertEqualsWithDelta(330, $pixOnly['totals']['gmv'], 0.01);
    }

    public function test_dashboard_filters_orders_by_order_facets_and_reversed_date_range(): void
    {
        $issuer = User::create([
            'name' => 'Emissor Filtro',
            'email' => 'filtro@example.com',
            'password' => 'secret',
            'role' => 'emissor',
        ]);
        $coupon = Coupon::create([
            'code' => 'FILTRO',
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ]);

        $matching = $this->createOrder([
            'status' => 'completed',
            'coupon_id' => $coupon->id,
            'departure_iata' => 'GRU',
            'arrival_iata' => 'REC',
            'device_type' => 'mobile',
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        $this->addFlight($matching, [
            'cia' => 'AZUL',
            'money_price' => '120.00',
            'tax' => '30.00',
        ]);
        $this->addPayment($matching, [
            'gateway' => 'c6bank',
            'payment_method' => 'credit_card',
            'status' => 'paid',
            'amount' => 150,
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        $matching->emission()->create([
            'issuer_id' => $issuer->id,
            'status' => 'assigned',
            'emission_value' => 0,
        ]);

        $nonMatching = $this->createOrder([
            'status' => 'completed',
            'departure_iata' => 'CNF',
            'arrival_iata' => 'VIX',
            'device_type' => 'desktop',
            'paid_at' => '2026-05-10 10:00:00',
        ]);
        $this->addFlight($nonMatching, [
            'cia' => 'GOL',
            'money_price' => '120.00',
            'tax' => '30.00',
        ]);
        $this->addPayment($nonMatching, [
            'gateway' => 'c6bank',
            'payment_method' => 'credit_card',
            'status' => 'paid',
            'amount' => 150,
            'paid_at' => '2026-05-10 10:00:00',
        ]);

        $dashboard = app(PaidOrdersMetricsService::class)->dashboard([
            'date_from' => '2026-05-31',
            'date_to' => '2026-05-01',
            'order_status' => 'completed',
            'airline' => 'AZUL',
            'device_type' => 'mobile',
            'issuer_id' => $issuer->id,
            'coupon' => 'with_coupon',
            'departure_iata' => ' gru ',
            'arrival_iata' => ' rec ',
            'gateway' => 'c6bank',
        ]);

        $this->assertSame(1, $dashboard['totals']['orders']);
        $this->assertSame('GRU -> REC', $dashboard['routes'][0]['label']);
        $this->assertSame('Cartão', $dashboard['payment_methods'][0]['label']);
        $this->assertSame('C6bank', $dashboard['gateways'][0]['label']);
    }
}
