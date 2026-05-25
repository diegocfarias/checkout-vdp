<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFlight;

class EmissionCostService
{
    public function bdsSummary(Order $order): array
    {
        $order->loadMissing('flights');
        $payingPassengers = max((int) $order->total_adults + (int) $order->total_children, 1);
        $flights = [];
        $totalPerPassenger = 0.0;

        foreach ($order->flights as $flight) {
            $cost = $this->directBdsCostForFlight($flight);
            if ($cost === null) {
                continue;
            }

            $totalPerPassenger += $cost;
            $flights[] = [
                'direction' => $flight->direction === 'outbound' ? 'Ida' : 'Volta',
                'label' => trim(strtoupper((string) ($flight->cia ?? $flight->operator ?? '')).' '.(string) $flight->flight_number),
                'cost' => $cost,
            ];
        }

        return [
            'has_bds' => ! empty($flights),
            'paying_passengers' => $payingPassengers,
            'flights' => $flights,
            'total_per_passenger' => round($totalPerPassenger, 2),
            'total_order' => round($totalPerPassenger * $payingPassengers, 2),
        ];
    }

    public function directBdsCostForFlight(OrderFlight $flight): ?float
    {
        if ($flight->source_provider !== 'bds_crawler') {
            return null;
        }

        $storedCost = $this->moneyToFloat($flight->provider_direct_cost);
        if ($storedCost > 0) {
            return round($storedCost, 2);
        }

        $base = $this->moneyToFloat($flight->price_money);
        $tax = $this->moneyToFloat($flight->tax ?? $flight->boarding_tax);
        $cost = $base + $tax;

        return $cost > 0 ? round($cost, 2) : null;
    }

    public function formatMoney(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    public function moneyToFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^\d,.\-]/', '', (string) $value) ?? '';
        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return 0.0;
        }

        if (str_contains($value, ',')) {
            return (float) str_replace(',', '.', str_replace('.', '', $value));
        }

        return (float) $value;
    }
}
