<?php

namespace App\Services;

use App\Models\OrderEmission;
use App\Models\OrderEmissionLog;
use App\Models\Setting;

class TravellinkEmissionProcessor
{
    public function __construct(
        private readonly TravellinkService $travellink
    ) {}

    public function process(OrderEmission $emission, ?int $userId = null): array
    {
        $emission->loadMissing(['order.flights', 'order.passengers']);

        $result = $this->travellink->issueOrder($emission->order);

        if ($result['dry_run'] ?? false) {
            OrderEmissionLog::create([
                'order_emission_id' => $emission->id,
                'action' => 'travellink_dry_run',
                'user_id' => $userId,
                'notes' => 'Modo teste ativo. Nenhuma chamada real de emissão foi executada.',
            ]);

            return $result;
        }

        $this->complete($emission, $result, $userId);

        return $result;
    }

    private function complete(OrderEmission $emission, array $result, ?int $userId): void
    {
        $now = now();
        $order = $emission->order;
        $order->loadMissing('flights');
        $loc = strtoupper(trim((string) ($result['localizador'] ?? '')));

        if ($loc === '') {
            throw new \RuntimeException('Travellink não retornou localizador para concluir o pedido.');
        }

        foreach ($order->flights as $flight) {
            $flight->update([
                'loc' => $loc,
                'paid_boarding_tax' => $this->moneyToFloat(
                    $flight->paid_boarding_tax ?? $flight->tax ?? $flight->boarding_tax ?? 0
                ),
            ]);
        }

        $emission->update([
            'status' => 'completed',
            'completed_at' => $now,
            'duration_seconds' => $emission->calculateDuration() ?? $emission->assigned_at?->diffInSeconds($now),
            'emission_value' => (float) Setting::get('emission_value_per_order', 0),
            'miles_cost_per_thousand' => 0,
            'emission_provider' => 'travellink',
        ]);

        $order->update([
            'status' => 'completed',
            'loc' => $loc,
        ]);

        $tickets = $result['tickets'] ?? [];
        OrderEmissionLog::create([
            'order_emission_id' => $emission->id,
            'action' => 'completed',
            'user_id' => $userId,
            'notes' => 'Emissão Travellink concluída. Localizador: '.$loc
                .(! empty($tickets) ? ' | Bilhetes: '.implode(', ', $tickets) : ''),
        ]);
    }

    private function moneyToFloat(mixed $value): float
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
