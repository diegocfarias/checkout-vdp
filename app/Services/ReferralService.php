<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    /**
     * Resolve um código digitado: retorna se é cupom, indicação ou nada.
     */
    public function resolveCode(string $code): ?array
    {
        $code = strtoupper(trim($code));

        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();
        if ($coupon) {
            return ['type' => 'coupon', 'model' => $coupon];
        }

        $affiliate = Customer::where('referral_code', $code)
            ->where('is_affiliate', true)
            ->first();

        if ($affiliate) {
            return ['type' => 'referral', 'model' => $affiliate];
        }

        return null;
    }

    /**
     * Valida se a indicação pode ser aplicada (afiliado ativo, sem autouso).
     */
    public function validateReferral(Customer $affiliate, string $payerDocument): array
    {
        if (! Setting::get('referral_enabled', false)) {
            return ['valid' => false, 'error' => 'Sistema de indicações desabilitado.'];
        }

        if (! $affiliate->isAffiliate()) {
            return ['valid' => false, 'error' => 'Código de indicação inválido.'];
        }

        $cleanPayer = preg_replace('/\D/', '', $payerDocument);
        $cleanAffiliate = $affiliate->getCleanDocument();

        if ($cleanPayer && $cleanAffiliate && $cleanPayer === $cleanAffiliate) {
            return ['valid' => false, 'error' => 'Você não pode usar seu próprio código de indicação.'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Retorna os percentuais efetivos (override do afiliado ou global).
     */
    public function getEffectiveRates(Customer $affiliate): array
    {
        return [
            'discount_pct' => (float) ($affiliate->affiliate_discount_pct ?? Setting::get('referral_discount_pct', 5)),
            'credit_pct' => (float) ($affiliate->affiliate_credit_pct ?? Setting::get('referral_credit_pct', 5)),
        ];
    }

    /**
     * Aplica o desconto de indicação e cria o registro de Referral.
     */
    public function applyReferralDiscount(
        Order $order,
        Customer $affiliate,
        float $baseTotal,
        string $payerDocument,
        ?int $referredCustomerId = null
    ): Referral {
        $rates = $this->getEffectiveRates($affiliate);
        $discountAmount = round($baseTotal * $rates['discount_pct'] / 100, 2);
        $creditAmount = round($baseTotal * $rates['credit_pct'] / 100, 2);

        $creditAvailableAt = $this->calculateCreditAvailableAt($order);

        $referral = Referral::create([
            'affiliate_id' => $affiliate->id,
            'referred_order_id' => $order->id,
            'referred_customer_id' => $referredCustomerId,
            'referred_document' => preg_replace('/\D/', '', $payerDocument),
            'referral_code_used' => $affiliate->referral_code,
            'order_base_total' => $baseTotal,
            'discount_pct' => $rates['discount_pct'],
            'discount_amount' => $discountAmount,
            'credit_pct' => $rates['credit_pct'],
            'credit_amount' => $creditAmount,
            'credit_status' => 'pending',
            'credit_available_at' => $creditAvailableAt,
            'status' => 'active',
        ]);

        $order->update([
            'referral_id' => $referral->id,
            'discount_amount' => $discountAmount,
        ]);

        return $referral;
    }

    /**
     * Calcula quando o crédito deve ser liberado.
     */
    private function calculateCreditAvailableAt(Order $order): \Carbon\Carbon
    {
        $mode = Setting::get('referral_credit_release_mode', 'after_purchase');
        $hours = (int) Setting::get('referral_credit_release_hours', 24);

        if ($mode === 'after_arrival') {
            $order->loadMissing('flightSearch');
            $search = $order->flightSearch;

            if ($search && $search->outbound_date) {
                return $search->outbound_date->copy()->endOfDay()->addHours($hours);
            }
        }

        return now()->addHours($hours);
    }

    /**
     * Saldo disponível do afiliado (calculado do ledger).
     */
    public function getAvailableBalance(Customer $customer): float
    {
        $credits = WalletTransaction::where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->sum('amount');

        $debits = WalletTransaction::where('customer_id', $customer->id)
            ->where('type', 'debit')
            ->sum('amount');

        $reversals = WalletTransaction::where('customer_id', $customer->id)
            ->where('type', 'reversal')
            ->sum('amount');

        return round((float) $credits - (float) $debits - (float) $reversals, 2);
    }

    /**
     * Saldo pendente (créditos ainda não liberados).
     */
    public function getPendingBalance(Customer $customer): float
    {
        return (float) Referral::where('affiliate_id', $customer->id)
            ->where('credit_status', 'pending')
            ->where('status', 'active')
            ->sum('credit_amount');
    }

    /**
     * Debita saldo da carteira com lock transacional.
     */
    public function debitWallet(Customer $customer, Order $order, float $amount): WalletTransaction
    {
        return DB::transaction(function () use ($customer, $order, $amount) {
            $balance = $this->getAvailableBalance($customer);

            if ($balance < $amount) {
                throw new \RuntimeException('Saldo insuficiente. Disponível: R$ ' . number_format($balance, 2, ',', '.'));
            }

            $newBalance = round($balance - $amount, 2);

            return WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Uso de créditos no pedido ' . $order->tracking_code,
                'order_id' => $order->id,
            ]);
        });
    }

    /**
     * Credita saldo ao liberar indicação.
     */
    public function creditWallet(Customer $customer, Referral $referral): WalletTransaction
    {
        return DB::transaction(function () use ($customer, $referral) {
            $balance = $this->getAvailableBalance($customer);
            $newBalance = round($balance + (float) $referral->credit_amount, 2);

            $tx = WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $referral->credit_amount,
                'balance_after' => $newBalance,
                'description' => 'Crédito por indicação — pedido ' . $referral->referredOrder->tracking_code,
                'referral_id' => $referral->id,
            ]);

            $referral->update([
                'credit_status' => 'available',
                'credit_released_at' => now(),
            ]);

            return $tx;
        });
    }

    /**
     * Reverte crédito de uma indicação (pedido cancelado/não pago).
     */
    public function reverseCredit(Referral $referral): void
    {
        DB::transaction(function () use ($referral) {
            if ($referral->credit_status === 'available') {
                $customer = $referral->affiliate;
                $balance = $this->getAvailableBalance($customer);
                $newBalance = round($balance - (float) $referral->credit_amount, 2);

                WalletTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => 'reversal',
                    'amount' => $referral->credit_amount,
                    'balance_after' => max(0, $newBalance),
                    'description' => 'Estorno de crédito — pedido cancelado',
                    'referral_id' => $referral->id,
                ]);
            }

            $referral->update([
                'credit_status' => 'reversed',
                'status' => 'reversed',
            ]);
        });
    }

    /**
     * Devolve créditos usados quando um pedido é cancelado/estornado.
     */
    public function refundWalletUsage(Order $order): void
    {
        if ((float) $order->wallet_amount_used <= 0) {
            return;
        }

        DB::transaction(function () use ($order) {
            $customer = $order->customer;
            if (! $customer) {
                return;
            }

            $balance = $this->getAvailableBalance($customer);
            $newBalance = round($balance + (float) $order->wallet_amount_used, 2);

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $order->wallet_amount_used,
                'balance_after' => $newBalance,
                'description' => 'Devolução de créditos — pedido ' . $order->tracking_code . ' cancelado',
                'order_id' => $order->id,
            ]);

            $order->update(['wallet_amount_used' => 0]);
        });
    }

    /**
     * Calcula o desconto de preview (para o endpoint AJAX).
     */
    public function previewReferralDiscount(Customer $affiliate, float $baseTotal): array
    {
        $rates = $this->getEffectiveRates($affiliate);
        $discount = round($baseTotal * $rates['discount_pct'] / 100, 2);

        return [
            'discount_pct' => $rates['discount_pct'],
            'discount_amount' => $discount,
            'new_total' => round($baseTotal - $discount, 2),
            'affiliate_name' => explode(' ', trim($affiliate->name))[0],
        ];
    }
}
