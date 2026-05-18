<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_resolve_code_prefers_prefixed_referral_over_coupon(): void
    {
        $affiliate = $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-ABC123',
        ]);
        $this->createCoupon(['code' => 'IND-ABC123']);

        $resolved = app(ReferralService::class)->resolveCode(' ind-abc123 ');

        $this->assertSame('referral', $resolved['type']);
        $this->assertSame($affiliate->id, $resolved['model']->id);
    }

    public function test_resolve_code_uses_coupon_before_unprefixed_referral(): void
    {
        $coupon = $this->createCoupon(['code' => 'ABC123']);
        $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-ABC123',
        ]);

        $resolved = app(ReferralService::class)->resolveCode(' abc 123 ');

        $this->assertSame('coupon', $resolved['type']);
        $this->assertSame($coupon->id, $resolved['model']->id);
    }

    public function test_resolve_code_returns_null_for_empty_or_unknown_codes(): void
    {
        $service = app(ReferralService::class);

        $this->assertNull($service->resolveCode('   '));
        $this->assertNull($service->resolveCode('UNKNOWN'));
    }

    public function test_validate_referral_requires_enabled_affiliate_and_prevents_self_use(): void
    {
        $service = app(ReferralService::class);
        $affiliate = $this->createCustomer([
            'document' => '123.456.789-00',
            'is_affiliate' => true,
            'referral_code' => 'IND-VALID',
        ]);

        $this->assertFalse($service->validateReferral($affiliate, '11122233344')['valid']);

        Setting::set('referral_enabled', true, 'boolean');
        $notAffiliate = $this->createCustomer();
        $this->assertFalse($service->validateReferral($notAffiliate, '11122233344')['valid']);

        $selfUse = $service->validateReferral($affiliate, '12345678900');
        $this->assertFalse($selfUse['valid']);
        $this->assertSame('Você não pode usar seu próprio código de indicação.', $selfUse['error']);

        $valid = $service->validateReferral($affiliate, '111.222.333-44');
        $this->assertTrue($valid['valid']);
        $this->assertNull($valid['error']);
    }

    public function test_get_effective_rates_uses_affiliate_overrides_or_global_defaults(): void
    {
        Setting::set('referral_discount_pct', '7.5');
        Setting::set('referral_credit_pct', '3.25');

        $withoutOverrides = $this->createCustomer();
        $withOverrides = $this->createCustomer([
            'affiliate_discount_pct' => 9,
            'affiliate_credit_pct' => 4.5,
        ]);

        $service = app(ReferralService::class);

        $this->assertSame([
            'discount_pct' => 7.5,
            'credit_pct' => 3.25,
        ], $service->getEffectiveRates($withoutOverrides));

        $this->assertSame([
            'discount_pct' => 9.0,
            'credit_pct' => 4.5,
        ], $service->getEffectiveRates($withOverrides));
    }

    public function test_apply_referral_discount_creates_referral_and_updates_order(): void
    {
        Carbon::setTestNow('2026-05-14 10:00:00');
        Setting::set('referral_discount_pct', '10');
        Setting::set('referral_credit_pct', '5');
        Setting::set('referral_credit_release_mode', 'after_purchase');
        Setting::set('referral_credit_release_hours', '48', 'integer');

        $affiliate = $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-AFF001',
        ]);
        $order = $this->createOrder();
        $referred = $this->createCustomer();

        $referral = app(ReferralService::class)->applyReferralDiscount(
            $order,
            $affiliate,
            1000,
            '111.222.333-44',
            $referred->id,
        );

        $this->assertSame($affiliate->id, $referral->affiliate_id);
        $this->assertSame($referred->id, $referral->referred_customer_id);
        $this->assertSame('11122233344', $referral->referred_document);
        $this->assertEqualsWithDelta(100, (float) $referral->discount_amount, 0.01);
        $this->assertEqualsWithDelta(50, (float) $referral->credit_amount, 0.01);
        $this->assertSame('pending', $referral->credit_status);
        $this->assertTrue($referral->credit_available_at->equalTo(Carbon::parse('2026-05-16 10:00:00')));

        $order->refresh();
        $this->assertSame($referral->id, $order->referral_id);
        $this->assertEqualsWithDelta(100, (float) $order->discount_amount, 0.01);
    }

    public function test_apply_referral_discount_can_release_after_arrival_date(): void
    {
        Setting::set('referral_discount_pct', '2');
        Setting::set('referral_credit_pct', '3');
        Setting::set('referral_credit_release_mode', 'after_arrival');
        Setting::set('referral_credit_release_hours', '12', 'integer');

        $search = FlightSearch::create([
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'outbound_date' => '2026-06-10',
            'trip_type' => 'oneway',
            'cabin' => 'EC',
            'adults' => 1,
        ]);
        $order = $this->createOrder(['flight_search_id' => $search->id]);
        $affiliate = $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-DATE01',
        ]);

        $referral = app(ReferralService::class)->applyReferralDiscount($order, $affiliate, 500, '11122233344');

        $this->assertTrue($referral->credit_available_at->equalTo(Carbon::parse('2026-06-11 11:59:59')));
    }

    public function test_wallet_balance_methods_sum_available_and_pending_amounts(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'description' => 'Credito',
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'debit',
            'amount' => 30,
            'balance_after' => 70,
            'description' => 'Debito',
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'reversal',
            'amount' => 20,
            'balance_after' => 50,
            'description' => 'Estorno',
        ]);
        WalletTransaction::create([
            'customer_id' => $otherCustomer->id,
            'type' => 'credit',
            'amount' => 999,
            'balance_after' => 999,
            'description' => 'Outro cliente',
        ]);

        $this->createReferral([
            'affiliate_id' => $customer->id,
            'credit_amount' => 15,
            'credit_status' => 'pending',
            'status' => 'active',
        ]);
        $this->createReferral([
            'affiliate_id' => $customer->id,
            'credit_amount' => 25,
            'credit_status' => 'pending',
            'status' => 'reversed',
        ]);

        $service = app(ReferralService::class);

        $this->assertEqualsWithDelta(50, $service->getAvailableBalance($customer), 0.01);
        $this->assertEqualsWithDelta(15, $service->getPendingBalance($customer), 0.01);
    }

    public function test_debit_wallet_creates_transaction_and_rejects_insufficient_balance(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder(['customer_id' => $customer->id]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'description' => 'Credito inicial',
        ]);

        $service = app(ReferralService::class);
        $transaction = $service->debitWallet($customer, $order, 35.55);

        $this->assertSame('debit', $transaction->type);
        $this->assertSame($order->id, $transaction->order_id);
        $this->assertEqualsWithDelta(64.45, (float) $transaction->balance_after, 0.01);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Saldo insuficiente');

        $service->debitWallet($customer, $order, 1000);
    }

    public function test_credit_wallet_releases_referral_and_updates_balance(): void
    {
        Carbon::setTestNow('2026-05-14 12:00:00');
        $customer = $this->createCustomer();
        $referral = $this->createReferral([
            'affiliate_id' => $customer->id,
            'credit_amount' => 22.5,
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 10,
            'balance_after' => 10,
            'description' => 'Credito anterior',
        ]);

        $transaction = app(ReferralService::class)->creditWallet($customer, $referral);

        $this->assertSame('credit', $transaction->type);
        $this->assertSame($referral->id, $transaction->referral_id);
        $this->assertEqualsWithDelta(32.5, (float) $transaction->balance_after, 0.01);
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'credit_status' => 'available',
        ]);
        $this->assertTrue($referral->fresh()->credit_released_at->equalTo(Carbon::parse('2026-05-14 12:00:00')));
    }

    public function test_reverse_credit_creates_reversal_for_available_referral(): void
    {
        $customer = $this->createCustomer();
        $referral = $this->createReferral([
            'affiliate_id' => $customer->id,
            'credit_status' => 'available',
            'status' => 'active',
            'credit_amount' => 15,
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 50,
            'balance_after' => 50,
            'description' => 'Credito anterior',
        ]);

        app(ReferralService::class)->reverseCredit($referral);

        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => 'reversal',
            'referral_id' => $referral->id,
            'amount' => 15,
            'balance_after' => 35,
        ]);
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'credit_status' => 'reversed',
            'status' => 'reversed',
        ]);
    }

    public function test_refund_wallet_usage_returns_credit_and_clears_order_amount(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder([
            'customer_id' => $customer->id,
            'wallet_amount_used' => 40,
        ]);
        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => 10,
            'balance_after' => 10,
            'description' => 'Credito anterior',
        ]);

        app(ReferralService::class)->refundWalletUsage($order);

        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => 'credit',
            'order_id' => $order->id,
            'amount' => 40,
            'balance_after' => 50,
        ]);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->wallet_amount_used, 0.01);
    }

    public function test_refund_wallet_usage_ignores_orders_without_wallet_or_customer(): void
    {
        $customer = $this->createCustomer();

        app(ReferralService::class)->refundWalletUsage($this->createOrder([
            'customer_id' => $customer->id,
            'wallet_amount_used' => 0,
        ]));
        app(ReferralService::class)->refundWalletUsage($this->createOrder([
            'wallet_amount_used' => 20,
        ]));

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_preview_referral_discount_returns_first_name_and_new_total(): void
    {
        $affiliate = $this->createCustomer([
            'name' => '  Maria Silva  ',
            'affiliate_discount_pct' => 12.5,
            'affiliate_credit_pct' => 5,
        ]);

        $preview = app(ReferralService::class)->previewReferralDiscount($affiliate, 800);

        $this->assertSame(12.5, $preview['discount_pct']);
        $this->assertEqualsWithDelta(100, $preview['discount_amount'], 0.01);
        $this->assertEqualsWithDelta(700, $preview['new_total'], 0.01);
        $this->assertSame('Maria', $preview['affiliate_name']);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => '000.000.000-00',
            'status' => 'active',
            'is_affiliate' => false,
        ], $attributes));
    }

    private function createCoupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'CUPOM'.fake()->unique()->numberBetween(1000, 9999),
            'type' => 'fixed',
            'value' => 10,
            'active' => true,
        ], $attributes));
    }

    private function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'total_adults' => 1,
            'total_children' => 0,
            'total_babies' => 0,
            'cabin' => 'EC',
            'departure_iata' => 'GRU',
            'arrival_iata' => 'SDU',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ], $attributes));
    }

    private function createReferral(array $attributes = []): Referral
    {
        $affiliate = isset($attributes['affiliate_id'])
            ? Customer::find($attributes['affiliate_id'])
            : $this->createCustomer([
                'is_affiliate' => true,
                'referral_code' => 'IND-'.fake()->unique()->bothify('????##'),
            ]);

        $order = $this->createOrder();

        return Referral::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'referred_order_id' => $order->id,
            'referred_document' => '11122233344',
            'referral_code_used' => $affiliate->referral_code ?: 'IND-TESTE1',
            'order_base_total' => 1000,
            'discount_pct' => 5,
            'discount_amount' => 50,
            'credit_pct' => 5,
            'credit_amount' => 50,
            'credit_status' => 'pending',
            'credit_available_at' => now(),
            'status' => 'active',
        ], $attributes));
    }
}
