<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;

class CancellationPolicyService
{
    public const REASONS = [
        'regret_24h' => 'Desisti da compra',
        'wrong_data' => 'Dados ou datas incorretas',
        'flight_cancelled' => 'Voo cancelado pela companhia',
        'schedule_change' => 'Alteracao relevante do voo',
        'duplicate_purchase' => 'Compra em duplicidade',
        'medical_or_personal' => 'Motivo medico ou pessoal',
        'other' => 'Outro motivo',
    ];

    public function evaluate(Order $order, ?string $reason = null): array
    {
        $order->loadMissing(['flightSearch', 'payments']);

        $firstDepartureDate = $this->firstDepartureDate($order);
        $purchaseReferenceAt = $this->purchaseReferenceAt($order);
        $hasConfirmedPayment = $this->hasConfirmedPayment($order);

        $within24Hours = $purchaseReferenceAt
            ? $purchaseReferenceAt->greaterThanOrEqualTo(now()->subHours(24))
            : false;

        $departureAtLeastSevenDaysAway = $firstDepartureDate
            ? now()->startOfDay()->diffInDays($firstDepartureDate->copy()->startOfDay(), false) >= 7
            : false;

        $withoutConfirmedPayment = ! $hasConfirmedPayment
            && in_array($order->status, ['pending', 'awaiting_payment', 'cancelled'], true);

        $involuntaryReason = in_array($reason, ['flight_cancelled', 'schedule_change'], true);
        $freeCancellationWindow = $hasConfirmedPayment && $within24Hours && $departureAtLeastSevenDaysAway;
        $withinPolicy = $withoutConfirmedPayment || $freeCancellationWindow || $involuntaryReason;

        $rule = match (true) {
            $withoutConfirmedPayment => 'Pedido sem pagamento confirmado: pode ser cancelado sem multa e sem estorno externo.',
            $freeCancellationWindow => 'Dentro da janela de cancelamento sem custo: ate 24 horas da compra e embarque em 7 dias ou mais.',
            $involuntaryReason => 'Alteracao ou cancelamento pela companhia: nossa equipe vai analisar as alternativas disponiveis.',
            default => 'Fora do prazo de cancelamento sem custo: cancelamentos voluntarios nao geram reembolso.',
        };

        return [
            'within_policy' => $withinPolicy,
            'priority' => $withinPolicy ? 'urgent' : 'normal',
            'rule' => $rule,
            'reason' => $reason,
            'reason_label' => $this->reasonLabel($reason),
            'has_confirmed_payment' => $hasConfirmedPayment,
            'free_cancellation_window' => $freeCancellationWindow,
            'without_confirmed_payment' => $withoutConfirmedPayment,
            'involuntary_reason' => $involuntaryReason,
            'within_24_hours' => $within24Hours,
            'departure_at_least_seven_days_away' => $departureAtLeastSevenDaysAway,
            'purchase_reference_at' => $purchaseReferenceAt?->toIso8601String(),
            'first_departure_date' => $firstDepartureDate?->toDateString(),
        ];
    }

    public function reasonLabel(?string $reason): string
    {
        return self::REASONS[$reason] ?? 'Outro motivo';
    }

    private function hasConfirmedPayment(Order $order): bool
    {
        if ($order->paid_at || in_array($order->status, ['awaiting_emission', 'completed'], true)) {
            return true;
        }

        return $order->payments->contains(fn ($payment): bool => $payment->status === 'paid' || $payment->paid_at !== null);
    }

    private function purchaseReferenceAt(Order $order): ?Carbon
    {
        if ($order->paid_at) {
            return $order->paid_at;
        }

        $paidPayment = $order->payments
            ->filter(fn ($payment): bool => $payment->status === 'paid' || $payment->paid_at !== null)
            ->sortBy(fn ($payment) => $payment->paid_at ?? $payment->created_at)
            ->first();

        return $paidPayment?->paid_at ?? $paidPayment?->created_at ?? $order->created_at;
    }

    private function firstDepartureDate(Order $order): ?Carbon
    {
        if (! $order->flightSearch?->outbound_date) {
            return null;
        }

        return $order->flightSearch->outbound_date instanceof Carbon
            ? $order->flightSearch->outbound_date
            : Carbon::parse($order->flightSearch->outbound_date);
    }
}
