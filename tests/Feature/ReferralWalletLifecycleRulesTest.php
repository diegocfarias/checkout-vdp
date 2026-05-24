<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Referral;
use App\Models\WalletTransaction;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class ReferralWalletLifecycleRulesTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    public function test_referral_credit_is_released_only_once_in_wallet_ledger(): void
    {
        $affiliate = $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-ONCE1',
        ]);
        $order = $this->createOrder(['status' => 'completed']);
        $referral = $this->createReferral($affiliate, $order, [
            'credit_amount' => 42.50,
            'credit_status' => 'pending',
            'status' => 'active',
        ]);

        $service = app(ReferralService::class);
        $first = $service->creditWallet($affiliate, $referral);
        $second = $service->creditWallet($affiliate, $referral->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'referral_id' => $referral->id,
            'amount' => 42.50,
            'balance_after' => 42.50,
        ]);
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'credit_status' => 'available',
            'status' => 'active',
        ]);
        $this->assertEqualsWithDelta(42.50, $service->getAvailableBalance($affiliate), 0.01);
    }

    public function test_order_cancellation_reverses_referral_credit_and_refunds_wallet_usage(): void
    {
        Mail::fake();

        $affiliate = $this->createCustomer([
            'is_affiliate' => true,
            'referral_code' => 'IND-CANCEL',
        ]);
        $buyer = $this->createCustomer(['email' => 'comprador@example.com']);
        $order = $this->createOrder([
            'customer_id' => $buyer->id,
            'status' => 'awaiting_emission',
            'wallet_amount_used' => 30.00,
        ]);
        $referral = $this->createReferral($affiliate, $order, [
            'credit_amount' => 50.00,
            'credit_status' => 'available',
            'status' => 'active',
            'credit_released_at' => now(),
        ]);

        WalletTransaction::create([
            'customer_id' => $affiliate->id,
            'type' => 'credit',
            'amount' => 50.00,
            'balance_after' => 50.00,
            'description' => 'Credito liberado',
            'referral_id' => $referral->id,
        ]);
        WalletTransaction::create([
            'customer_id' => $buyer->id,
            'type' => 'credit',
            'amount' => 80.00,
            'balance_after' => 80.00,
            'description' => 'Saldo original',
        ]);
        WalletTransaction::create([
            'customer_id' => $buyer->id,
            'type' => 'debit',
            'amount' => 30.00,
            'balance_after' => 50.00,
            'description' => 'Uso no pedido',
            'order_id' => $order->id,
        ]);

        $order->update(['status' => 'cancelled']);
        $order->refresh();

        $this->assertEqualsWithDelta(0, (float) $order->wallet_amount_used, 0.01);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $buyer->id,
            'type' => 'credit',
            'order_id' => $order->id,
            'amount' => 30.00,
            'balance_after' => 80.00,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $affiliate->id,
            'type' => 'reversal',
            'referral_id' => $referral->id,
            'amount' => 50.00,
            'balance_after' => 0.00,
        ]);
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'credit_status' => 'reversed',
            'status' => 'reversed',
        ]);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(1, WalletTransaction::where('customer_id', $buyer->id)
            ->where('type', 'credit')
            ->where('order_id', $order->id)
            ->count());
        $this->assertSame(1, WalletTransaction::where('customer_id', $affiliate->id)
            ->where('type', 'reversal')
            ->where('referral_id', $referral->id)
            ->count());
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Cliente Teste',
            'email' => fake()->unique()->safeEmail(),
            'document' => fake()->numerify('###########'),
            'status' => 'active',
            'is_affiliate' => false,
        ], $attributes));
    }

    private function createReferral(Customer $affiliate, $order, array $attributes = []): Referral
    {
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
