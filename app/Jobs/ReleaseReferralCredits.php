<?php

namespace App\Jobs;

use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseReferralCredits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ReferralService $referralService): void
    {
        $referrals = Referral::pendingRelease()
            ->with(['affiliate', 'referredOrder'])
            ->get();

        foreach ($referrals as $referral) {
            try {
                $order = $referral->referredOrder;

                if (! $order || in_array($order->status, ['cancelled', 'pending'])) {
                    $referralService->reverseCredit($referral);
                    Log::info('ReleaseReferralCredits: crédito revertido (pedido cancelado/pendente)', [
                        'referral_id' => $referral->id,
                        'order_status' => $order?->status,
                    ]);

                    continue;
                }

                if (! in_array($order->status, ['awaiting_emission', 'completed'])) {
                    continue;
                }

                $referralService->creditWallet($referral->affiliate, $referral);

                Log::info('ReleaseReferralCredits: crédito liberado', [
                    'referral_id' => $referral->id,
                    'affiliate_id' => $referral->affiliate_id,
                    'amount' => $referral->credit_amount,
                ]);
            } catch (\Throwable $e) {
                Log::error('ReleaseReferralCredits: falha ao processar', [
                    'referral_id' => $referral->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
